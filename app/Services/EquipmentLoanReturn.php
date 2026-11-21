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
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EquipmentLoanReturn
{
    private function authorize(User $actor, EquipmentLoan $loan): User
    {
        $fresh = User::with('role')->useWritePdo()->findOrFail($actor->id);
        Gate::forUser($fresh)->authorize('recordReturn', $loan);

        return $fresh;
    }

    /** Return the immutable batch receipt, including on an exact replay. */
    public function record(User $actor, string $token, array $input, array $photos = []): EquipmentAuditEvent
    {
        return EquipmentErrors::run(fn () => $this->performRecord($actor, $token, $input, photos: $photos));
    }

    /** Use a caller-owned transaction. The caller must roll back on any failure. */
    public function recordInTransaction(User $actor, string $token, array $input, array $photos = []): EquipmentAuditEvent
    {
        return EquipmentErrors::run(fn () => $this->performRecord($actor, $token, $input, nested: true, photos: $photos));
    }

    private function performRecord(User $actor, string $token, array $input, bool $nested = false, array $photos = []): EquipmentAuditEvent
    {
        // A duplicate-key recovery must read outside the entire failed transaction.
        if ($nested ? DB::transactionLevel() < 1 : DB::transactionLevel() !== 0) {
            throw new \LogicException('Return requires its own transaction.');
        }

        $token = strtolower($token);
        Validator::make(['token' => $token], ['token' => ['required', 'uuid']])->validate();
        $data = $this->canonicalPayload($input);
        if ($photos) {
            $manifest = [];
            foreach ($photos as $id => $photo) {
                $manifest[(int) $id] = $photo['sha256'];
            }
            ksort($manifest);
            if (count($photos) > 10 || array_keys($manifest) !== array_column($data['items'], 'item_id')) {
                $this->conflict('Setiap unit wajib memiliki foto. Maksimal 10 unit per batch foto individual.');
            }
            $data['format'] = 'return-v2';
            $data['evidence'] = $manifest;
        }
        $loan = EquipmentLoan::useWritePdo()->findOrFail($data['loan_id']);
        $actor = $this->authorize($actor, $loan);
        $key = 'users:'.$actor->id;
        $hash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        try {
            return DB::transaction(function () use ($actor, $token, $key, $hash, $data, $photos): EquipmentAuditEvent {
                // No consistent read is issued inside this transaction before this lock.
                $loan = EquipmentLoan::lockForUpdate()->findOrFail($data['loan_id']);
                $actor = $this->authorize($actor, $loan);

                // The first snapshot read occurs after the header lock was acquired.
                // Same-loan requests therefore see the preceding committed receipt.
                // Do not gap-lock a token which does not yet exist.
                if ($receipt = $this->replay($token, $key, $hash, $loan->id)) {
                    return $receipt;
                }
                if (! $photos && EquipmentPhotos::enabled()) {
                    $this->conflict('Bukti foto wajib untuk setiap unit yang dikembalikan.');
                }
                if ($loan->version !== $data['expected_version']) {
                    throw EquipmentOperationException::stale('items');
                }

                $ids = array_column($data['items'], 'item_id');
                // Item membership and its unit reference are immutable. The header
                // serializes changes to this loan; lock units before locking details.
                $references = $loan->items()->whereIn('id', $ids)->get(['id', 'inventory_unit_id'])->keyBy('id');
                if ($references->count() !== count($ids)) {
                    $this->conflict('Ada item yang tidak ditemukan atau bukan milik transaksi ini.');
                }
                $unitIds = $references->pluck('inventory_unit_id')->unique()->sort(SORT_NUMERIC)->values();
                $units = [];
                foreach ($unitIds as $unitId) {
                    $unit = InventoryUnit::lockForUpdate()->find($unitId);
                    if (! $unit) {
                        $this->conflict('Unit inventaris tidak ditemukan.');
                    }
                    $units[$unitId] = $unit;
                }

                $details = [];
                $now = CarbonImmutable::now('UTC');
                // canonicalPayload sorted item IDs numerically, so detail locks
                // always follow all unit locks, in ascending detail ID order.
                foreach ($data['items'] as $position => $item) {
                    $detail = EquipmentLoanItem::where('loan_id', $loan->id)->lockForUpdate()->find($item['item_id']);
                    if (! $detail || $detail->inventory_unit_id !== $references[$detail->id]->inventory_unit_id) {
                        $this->conflict('Referensi item berubah. Muat ulang form.');
                    }
                    if ($detail->returned_at !== null) {
                        $this->conflict('Ada item yang sudah kembali. Seluruh batch dibatalkan.');
                    }
                    $returnedAt = CarbonImmutable::parse($item['returned_at'], 'UTC');
                    if ($returnedAt->lt($loan->borrowed_at) || $returnedAt->gt($now)) {
                        throw ValidationException::withMessages([
                            'items.'.$position.'.returned_at' => 'Waktu kembali harus antara waktu pinjam dan waktu sekarang.',
                        ]);
                    }
                    // Recovery uses this return path, preserving the loss record
                    // and keeping the unit inactive pending separate inspection.
                    if ($detail->lost_at !== null && $returnedAt->lt($detail->lost_at)) {
                        $this->conflict('Waktu kembali mendahului laporan kehilangan.');
                    }
                    $needsNote = $item['condition_in'] !== 'good'
                        || $returnedAt->gt($loan->due_at)
                        || $returnedAt->lt($now->startOfMinute())
                        || $detail->lost_at !== null;
                    if ($needsNote && $item['return_notes'] === null) {
                        throw ValidationException::withMessages([
                            'items.'.$position.'.return_notes' => 'Catatan wajib untuk kerusakan, keterlambatan, tanggal kembali lampau, atau alat ditemukan kembali.',
                        ]);
                    }
                    $details[$detail->id] = $detail;
                }

                // All membership, version, state and date checks finish before
                // the first write. A failed save/audit still rolls back every row.
                $before = ['version' => $loan->version, 'items' => []];
                $results = [];
                foreach ($data['items'] as $item) {
                    $detail = $details[$item['item_id']];
                    $unit = $units[$detail->inventory_unit_id];
                    $before['items'][] = [
                        'item_id' => $detail->id,
                        'unit_id' => $unit->id,
                        'unit_condition' => $unit->condition,
                        'unit_version' => $unit->version,
                        'unit_is_active' => $unit->is_active,
                        'returned_at' => null,
                    ];
                    $detail->returned_at = $item['returned_at'];
                    $detail->condition_in = $item['condition_in'];
                    $detail->return_notes = $item['return_notes'];
                    $detail->due_at_on_return = $loan->due_at;
                    $detail->received_by = $actor->id;
                    $detail->received_by_name = $actor->name;
                    $detail->updated_at = $now;
                    $detail->save(); // MySQL releases active_unit_id automatically.

                    $unit->condition = $item['condition_in'];
                    $unit->version++;
                    if ($detail->lost_at !== null) {
                        $unit->is_active = false;
                    }
                    // Never reactivate inactive units, even on a good return.
                    $unit->updated_at = $now;
                    $unit->save();
                    $results[] = [
                        'item_id' => $detail->id,
                        'unit_id' => $unit->id,
                        'returned_at' => $item['returned_at'],
                        'condition_in' => $item['condition_in'],
                        'return_notes' => $item['return_notes'],
                        'due_at_on_return' => $loan->due_at->format('Y-m-d H:i:s.u'),
                        'received_by' => $actor->id,
                        'received_by_name' => $actor->name,
                        'unit_version' => $unit->version,
                        'unit_is_active' => $unit->is_active,
                        'was_late' => $detail->returned_at->gt($loan->due_at),
                    ];
                }
                $loan->version++;
                $loan->updated_at = $now;
                $loan->save();
                $remaining = $loan->items()->whereNull('returned_at')->count();

                $receipt = new EquipmentAuditEvent;
                $receipt->loan_id = $loan->id;
                $receipt->event_type = 'returned';
                $receipt->operation_token = $token;
                $receipt->operation_actor_key = $key;
                $receipt->operation_payload_hash = $hash;
                $receipt->actor_id = $actor->id;
                $receipt->actor_name = $actor->name;
                $receipt->reason = 'Penerimaan pengembalian alat';
                $receipt->before_data = $before;
                $receipt->after_data = [
                    'version' => $loan->version,
                    'items' => $results,
                    'remaining_items' => $remaining,
                    'status' => $remaining === 0 ? 'returned' : 'partially_returned',
                    'overdue' => $remaining > 0 && $loan->due_at->lt($now),
                ];
                $receipt->created_at = $now;
                $receipt->save();
                $normalized = EquipmentStructure::installed()
                    ? ['return_receipt_id' => EquipmentStructure::recordReceipt(DB::connection(), $receipt, $results)]
                    : [];
                foreach ($photos as $itemId => $photo) {
                    $photoId = DB::table('equipment_return_photos')->insertGetId($photo + ['receipt_id' => $receipt->id] + $normalized);
                    DB::table('equipment_return_photo_items')->insert(['photo_id' => $photoId, 'loan_item_id' => $itemId]);
                }

                return $receipt;
            }, $nested ? 1 : 3);
        } catch (QueryException $exception) {
            if ($nested) {
                throw $exception;
            }
            // A token reused on a different loan is not serialized by our header.
            // UNIQUE operation_token arbitrates it; first roll back all changes,
            // then read the winner outside the failed transaction/snapshot.
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
        if ($receipt->event_type !== 'returned' || $receipt->loan_id !== $loanId
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

    /** Normalize only the request; never hash mutable master/header state. */
    public function canonicalPayload(array $input): array
    {
        Validator::make($input, [
            'loan_id' => ['required', 'integer', 'min:1'],
            'expected_version' => ['required', 'integer', 'min:1'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*' => ['required', 'array'],
            'items.*.item_id' => ['required', 'integer', 'min:1', 'distinct'],
            'items.*.returned_at' => ['required', 'date_format:Y-m-d\TH:i,Y-m-d\TH:i:s'],
            'items.*.condition_in' => ['required', Rule::in(['good', 'minor_damage', 'major_damage'])],
            'items.*.return_notes' => ['nullable', 'string', 'max:5000'],
        ])->validate();

        $items = [];
        foreach ($input['items'] as $item) {
            $date = $item['returned_at'];
            $format = strlen($date) === 16 ? '!Y-m-d\TH:i' : '!Y-m-d\TH:i:s';
            $returnedAt = CarbonImmutable::createFromFormat($format, $date, 'Asia/Jakarta')->utc();
            $notes = trim(str_replace(["\r\n", "\r"], "\n", $item['return_notes'] ?? ''));
            $items[] = [
                'item_id' => (int) $item['item_id'],
                'returned_at' => $returnedAt->format('Y-m-d H:i:s.u'),
                'condition_in' => $item['condition_in'],
                'return_notes' => $notes === '' ? null : $notes,
            ];
        }
        usort($items, fn ($a, $b) => $a['item_id'] <=> $b['item_id']);

        return [
            'format' => 'return-v1',
            'loan_id' => (int) $input['loan_id'],
            'expected_version' => (int) $input['expected_version'],
            'items' => $items,
        ];
    }
}
