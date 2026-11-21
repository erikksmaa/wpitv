<?php

namespace App\Services;

use App\Exceptions\EquipmentOperationException;
use App\Models\EquipmentAuditEvent;
use App\Models\EquipmentLoan;
use App\Models\EquipmentLoanItem;
use App\Models\InventoryUnit;
use App\Models\User;
use App\Support\EquipmentErrors;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class EquipmentLoanLoss
{
    private function authorize(User $actor, EquipmentLoan $loan): User
    {
        $fresh = User::with('role')->useWritePdo()->findOrFail($actor->id);
        Gate::forUser($fresh)->authorize('reportLoss', $loan);

        return $fresh;
    }

    /** Return the original immutable receipt on an exact replay. */
    public function record(User $actor, string $token, array $input): EquipmentAuditEvent
    {
        return EquipmentErrors::run(fn () => $this->performRecord($actor, $token, $input));
    }

    /** Use a caller-owned transaction. The caller must roll back on any failure. */
    public function recordInTransaction(User $actor, string $token, array $input): EquipmentAuditEvent
    {
        return EquipmentErrors::run(fn () => $this->performRecord($actor, $token, $input, nested: true));
    }

    private function performRecord(User $actor, string $token, array $input, bool $nested = false): EquipmentAuditEvent
    {
        if ($nested ? DB::transactionLevel() < 1 : DB::transactionLevel() !== 0) {
            throw new \LogicException('Loss reporting requires its own transaction.');
        }
        $token = strtolower($token);
        Validator::make(['token' => $token], ['token' => ['required', 'uuid']])->validate();
        $data = $this->canonicalPayload($input);
        $actor = $this->authorize($actor, EquipmentLoan::useWritePdo()->findOrFail($data['loan_id']));
        $key = 'users:'.$actor->id;
        $hash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        try {
            return DB::transaction(function () use ($actor, $token, $data, $key, $hash): EquipmentAuditEvent {
                // Acquire the header before the first consistent read. Requests
                // for this loan see the receipt committed by a preceding holder.
                $loan = EquipmentLoan::lockForUpdate()->findOrFail($data['loan_id']);
                $actor = $this->authorize($actor, $loan);
                if ($receipt = $this->replay($token, $key, $hash, $loan->id)) {
                    return $receipt;
                }
                if ($loan->version !== $data['expected_version']) {
                    throw EquipmentOperationException::stale('items');
                }

                $references = $loan->items()->whereIn('id', array_column($data['items'], 'item_id'))
                    ->get(['id', 'inventory_unit_id'])->keyBy('id');
                if ($references->count() !== count($data['items'])) {
                    $this->conflict('Ada item yang tidak ditemukan atau bukan milik transaksi ini.');
                }
                // Header -> every unit (ascending ID) -> every detail (ascending ID).
                // Item membership and unit references are immutable in the application.
                $units = [];
                foreach ($references->pluck('inventory_unit_id')->unique()->sort(SORT_NUMERIC) as $unitId) {
                    $unit = InventoryUnit::lockForUpdate()->find($unitId);
                    if (! $unit) {
                        $this->conflict('Unit inventaris tidak ditemukan.');
                    }
                    $units[$unitId] = $unit;
                }
                $details = [];
                $now = CarbonImmutable::now('UTC');
                foreach ($data['items'] as $position => $item) {
                    $detail = EquipmentLoanItem::where('loan_id', $loan->id)->lockForUpdate()->find($item['item_id']);
                    if (! $detail || $detail->inventory_unit_id !== $references[$detail->id]->inventory_unit_id) {
                        $this->conflict('Referensi item berubah. Muat ulang form.');
                    }
                    if ($detail->returned_at !== null || $detail->lost_at !== null) {
                        $this->conflict('Ada item yang sudah kembali atau sudah dilaporkan hilang. Seluruh batch dibatalkan.');
                    }
                    $lostAt = CarbonImmutable::parse($item['lost_at'], 'UTC');
                    if ($lostAt->lt($loan->borrowed_at) || $lostAt->gt($now)) {
                        throw ValidationException::withMessages([
                            'items.'.$position.'.lost_at' => 'Waktu kehilangan harus antara waktu pinjam dan waktu sekarang.',
                        ]);
                    }
                    $details[$detail->id] = $detail;
                }

                // Finish validation of the whole batch before writing anything.
                $before = ['version' => $loan->version, 'items' => []];
                $results = [];
                foreach ($data['items'] as $item) {
                    $detail = $details[$item['item_id']];
                    $unit = $units[$detail->inventory_unit_id];
                    $before['items'][] = [
                        'item_id' => $detail->id, 'unit_id' => $unit->id,
                        'lost_at' => null, 'loss_notes' => null,
                        'unit_is_active' => $unit->is_active, 'unit_version' => $unit->version,
                    ];
                    $detail->lost_at = $item['lost_at'];
                    $detail->loss_notes = $item['loss_notes'];
                    $detail->updated_at = $now;
                    $detail->save();
                    // Leave returned_at, condition_out, and the initial snapshot
                    // untouched: generated active_unit_id must remain occupied.
                    $unit->is_active = false;
                    $unit->version++;
                    $unit->updated_at = $now;
                    $unit->save();
                    $results[] = [
                        'item_id' => $detail->id, 'unit_id' => $unit->id,
                        'lost_at' => $item['lost_at'], 'loss_notes' => $item['loss_notes'],
                        'returned_at' => null, 'unit_is_active' => false, 'unit_version' => $unit->version,
                    ];
                }
                $loan->version++;
                $loan->updated_at = $now;
                $loan->save();
                $counts = $loan->items()->get(['returned_at']);
                $remaining = $counts->whereNull('returned_at')->count();

                $receipt = new EquipmentAuditEvent;
                $receipt->loan_id = $loan->id;
                $receipt->event_type = 'lost';
                $receipt->operation_token = $token;
                $receipt->operation_actor_key = $key;
                $receipt->operation_payload_hash = $hash;
                $receipt->actor_id = $actor->id;
                $receipt->actor_name = $actor->name;
                $receipt->reason = 'Laporan kehilangan alat; alasan per item tercatat pada batch';
                $receipt->before_data = $before;
                $receipt->after_data = [
                    'version' => $loan->version, 'items' => $results,
                    'remaining_items' => $remaining, 'unresolved_loss' => true,
                    'status' => $remaining < $counts->count() ? 'partially_returned' : 'active',
                    'overdue' => $loan->due_at->lt($now),
                ];
                $receipt->created_at = $now;
                $receipt->save();

                return $receipt;
            }, $nested ? 1 : 3);
        } catch (QueryException $exception) {
            if ($nested) {
                throw $exception;
            }
            // Cross-loan token collisions are arbitrated by UNIQUE operation_token.
            // DB::transaction has rolled back the entire batch before this fresh read.
            if (($exception->errorInfo[1] ?? null) === 1062) {
                $this->authorize($actor, EquipmentLoan::useWritePdo()->findOrFail($data['loan_id']));
                if ($receipt = $this->replay($token, $key, $hash, $data['loan_id'])) {
                    return $receipt;
                }
                throw EquipmentOperationException::duplicate('items');
            }
            if (in_array($exception->errorInfo[1] ?? null, [1205, 1213], true)) {
                throw EquipmentOperationException::concurrency('items');
            }
            throw EquipmentErrors::sanitize($exception);
        }
    }

    private function replay(string $token, string $key, string $hash, int $loanId): ?EquipmentAuditEvent
    {
        $receipt = EquipmentAuditEvent::useWritePdo()->where('operation_token', $token)->first();
        if (! $receipt) {
            return null;
        }
        if ($receipt->event_type !== 'lost' || (int) $receipt->loan_id !== $loanId
            || $receipt->operation_actor_key !== $key
            || ! is_string($receipt->operation_payload_hash)
            || ! hash_equals($receipt->operation_payload_hash, $hash)) {
            $this->conflict('Token sudah digunakan untuk transaksi, petugas, atau data berbeda.');
        }

        return $receipt;
    }

    private function conflict(string $message): never
    {
        throw ValidationException::withMessages(['items' => $message]);
    }

    public function canonicalPayload(array $input): array
    {
        Validator::make($input, [
            'loan_id' => ['required', 'integer', 'min:1'],
            'expected_version' => ['required', 'integer', 'min:1'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*' => ['required', 'array'],
            'items.*.item_id' => ['required', 'integer', 'min:1', 'distinct'],
            'items.*.lost_at' => ['required', 'date_format:Y-m-d\TH:i,Y-m-d\TH:i:s'],
            'items.*.loss_notes' => ['required', 'string', 'max:5000'],
        ])->validate();
        $items = [];
        foreach ($input['items'] as $item) {
            $format = strlen($item['lost_at']) === 16 ? '!Y-m-d\TH:i' : '!Y-m-d\TH:i:s';
            $lostAt = CarbonImmutable::createFromFormat($format, $item['lost_at'], 'Asia/Jakarta')->utc();
            $items[] = [
                'item_id' => (int) $item['item_id'],
                'lost_at' => $lostAt->format('Y-m-d H:i:s.u'),
                'loss_notes' => trim(str_replace(["\r\n", "\r"], "\n", $item['loss_notes'])),
            ];
        }
        usort($items, fn ($a, $b) => $a['item_id'] <=> $b['item_id']);

        return [
            'format' => 'loss-v1', 'loan_id' => (int) $input['loan_id'],
            'expected_version' => (int) $input['expected_version'], 'items' => $items,
        ];
    }
}
