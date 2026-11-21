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
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EquipmentLoanCheckout
{
    private function actor(User $actor): User
    {
        $fresh = User::with('role')->findOrFail($actor->id);
        Gate::forUser($fresh)->authorize('create', EquipmentLoan::class);

        return $fresh;
    }

    public function checkout(User $actor, string $token, array $input): EquipmentLoan
    {
        return EquipmentErrors::run(fn () => $this->performCheckout($actor, $token, $input));
    }

    /** Use a caller-owned transaction. The caller must roll back on any failure. */
    public function checkoutInTransaction(User $actor, string $token, array $input): EquipmentLoan
    {
        return EquipmentErrors::run(fn () => $this->performCheckout($actor, $token, $input, nested: true));
    }

    private function performCheckout(User $actor, string $token, array $input, bool $nested = false): EquipmentLoan
    {
        // Recovery reads must occur after the entire failed transaction has rolled back.
        if ($nested ? DB::transactionLevel() < 1 : DB::transactionLevel() !== 0) {
            throw new \LogicException('Checkout requires its own transaction.');
        }
        $actor = $this->actor($actor);
        $token = strtolower($token);
        Validator::make(['token' => $token], ['token' => 'required|uuid'])->validate();
        $data = $this->canonicalPayload($input);
        $hash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $key = 'users:'.$actor->id;
        if ($existing = $this->replay($token, $key, $hash)) {
            return $existing;
        }
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                return DB::transaction(function () use ($actor, $token, $data, $hash, $key) {
                    $actor = $this->actor($actor);
                    // Ascending explicit row locks avoid reliance on optimizer lock order.
                    $units = [];
                    $ids = array_column($data['items'], 'unit_id');
                    $parents = DB::table('inventory_unit_members')->whereIn('unit_id', $ids)->pluck('parent_id', 'unit_id');
                    if (EquipmentStructure::installed()) {
                        $parents = DB::table('inventory_units')->whereIn('id', $ids)->whereNotNull('item_id')->pluck('item_id', 'id')->union($parents);
                    }
                    $locked = [];
                    foreach (collect($ids)->merge($parents->values())->unique()->sort() as $id) {
                        $locked[$id] = InventoryUnit::lockForUpdate()->find($id);
                    }
                    $masters = [];
                    foreach ($data['items'] as $item) {
                        $unit = $locked[$item['unit_id']];
                        $parent = $parents->get($item['unit_id'], $item['unit_id']);
                        $master = $unit?->item_id === null ? null : ($masters[$unit->item_id] ??= $unit->item()->lockForUpdate()->firstOrFail());
                        $enabled = $master?->is_active ?? $locked[$parent]?->is_active;
                        if (! $unit || ! $enabled || ! $unit->is_active || $unit->condition !== 'good') {
                            throw ValidationException::withMessages(['items' => 'Unit berubah atau tidak layak dipinjam. Muat ulang pilihan.']);
                        }
                        if ($unit->version !== $item['version']) {
                            throw EquipmentOperationException::stale('items');
                        }
                        if ($unit->loanItems()->whereNull('returned_at')->lockForUpdate()->first(['id'])) {
                            throw ValidationException::withMessages(['items' => 'Salah satu unit sudah dipinjam. Tidak ada item yang disimpan.']);
                        }
                        $units[$unit->id] = $unit;
                    }
                    $loan = new EquipmentLoan;
                    foreach (['borrower_name', 'student_number', 'study_program', 'whatsapp_number', 'purpose', 'notes', 'borrowed_at', 'due_at'] as $field) {
                        $loan->{$field} = $data[$field];
                    }
                    $loan->initial_due_at = $data['due_at'];
                    $loan->loan_code = 'PJM-'.now('Asia/Jakarta')->format('Ymd').'-'.strtoupper(Str::random(16));
                    $loan->submission_token = $token;
                    $loan->checkout_actor_key = $key;
                    $loan->checkout_payload_hash = $hash;
                    $loan->recorded_by = $actor->id;
                    $loan->recorded_by_name = $actor->name;
                    $loan->version = 1;
                    $loan->save();
                    $ids = [];
                    foreach ($data['items'] as $inputItem) {
                        $unit = $units[$inputItem['unit_id']];
                        $detail = new EquipmentLoanItem;
                        $detail->loan_id = $loan->id;
                        $detail->inventory_unit_id = $unit->id;
                        $snapshot = $unit->only(['inventory_code', 'name', 'brand', 'model', 'serial_number', 'storage_location']);
                        $snapshot['category'] = $unit->category?->name;
                        if ($unit->item_id !== null && ($master = $masters[$unit->item_id] ?? null)) {
                            $snapshot = array_replace($snapshot, $master->only(['name', 'brand', 'model']), ['category' => $master->category?->name]);
                        }
                        $detail->unit_snapshot = $snapshot;
                        $detail->condition_out = $unit->condition;
                        $detail->checkout_notes = $inputItem['checkout_notes'];
                        $detail->save(); // active_unit_id is generated by MySQL, never assigned here.
                        $ids[] = $detail->id;
                    }
                    $audit = new EquipmentAuditEvent;
                    $audit->loan_id = $loan->id;
                    $audit->event_type = 'checkout';
                    $audit->operation_token = $token;
                    $audit->operation_actor_key = $key;
                    $audit->operation_payload_hash = $hash;
                    $audit->actor_id = $actor->id;
                    $audit->actor_name = $actor->name;
                    $audit->reason = 'Serah terima peminjaman alat';
                    $audit->after_data = ['loan_code' => $loan->loan_code, 'item_ids' => $ids, 'due_at' => $data['due_at']];
                    $audit->save();

                    return $loan;
                }, $nested ? 1 : 3);
            } catch (QueryException|ValidationException|EquipmentOperationException $exception) {
                if ($nested) {
                    throw $exception;
                }
                // Fresh read after rollback handles same-token requests waiting on unit locks.
                $this->actor($actor);
                if ($existing = $this->replay($token, $key, $hash)) {
                    return $existing;
                }
                if ($exception instanceof QueryException && ($exception->errorInfo[1] ?? null) === 1062) {
                    $message = $exception->errorInfo[2] ?? '';
                    if (str_contains($message, 'equipment_loans_loan_code_unique') && $attempt < 2) {
                        continue;
                    }
                    if (str_contains($message, 'active_unit_id')) {
                        throw EquipmentOperationException::unitBorrowed();
                    }
                    throw EquipmentOperationException::duplicate('items');
                }
                throw EquipmentErrors::sanitize($exception);
            }
        }
        throw new \LogicException('Checkout retry exhausted.');
    }

    private function replay(string $token, string $key, string $hash): ?EquipmentLoan
    {
        $loan = EquipmentLoan::where('submission_token', $token)->first();
        $receipt = EquipmentAuditEvent::where('operation_token', $token)->first();
        // A winner can commit between these autocommit reads.
        if ($receipt && ! $loan) {
            $loan = EquipmentLoan::where('submission_token', $token)->first();
        }
        if (! $receipt && ! $loan) {
            return null;
        }
        if (! $loan || ! $receipt || $receipt->event_type !== 'checkout' || $receipt->loan_id !== $loan->id
            || $loan->checkout_actor_key !== $key || $receipt->operation_actor_key !== $key
            || ! hash_equals($loan->checkout_payload_hash, $hash) || ! hash_equals($receipt->operation_payload_hash, $hash)) {
            throw ValidationException::withMessages(['items' => 'Token sudah digunakan untuk petugas atau data berbeda.']);
        }

        return $loan;
    }

    public function canonicalPayload(array $input): array
    {
        $text = fn ($v) => is_string($v) ? trim(str_replace(["\r\n", "\r"], "\n", $v)) : $v;
        foreach (['borrower_name', 'student_number', 'study_program', 'whatsapp_number', 'purpose', 'notes'] as $field) {
            $input[$field] = $text($input[$field] ?? null);
        }
        if (is_string($input['whatsapp_number'])) {
            $input['whatsapp_number'] = preg_replace('/[\s()+-]/', '', $input['whatsapp_number']);
            if (str_starts_with($input['whatsapp_number'], '08')) {
                $input['whatsapp_number'] = '62'.substr($input['whatsapp_number'], 1);
            }
        }
        Validator::make($input, [
            'borrower_name' => 'required|string|max:150', 'student_number' => 'required|string|max:30|regex:/\A[A-Za-z0-9.-]+\z/',
            'study_program' => 'required|string|max:150', 'whatsapp_number' => 'required|string|regex:/\A[1-9][0-9]{7,14}\z/',
            'purpose' => 'required|string|max:3000', 'notes' => 'nullable|string|max:5000',
            'borrowed_at' => 'required|date_format:Y-m-d,Y-m-d\TH:i', 'due_at' => 'required|date_format:Y-m-d,Y-m-d\TH:i',
            'items' => 'required|array|min:1|max:50', 'items.*.unit_id' => 'required|integer|min:1|distinct',
            'items.*.version' => 'required|integer|min:1', 'items.*.checkout_notes' => 'nullable|string|max:3000',
        ])->validate();
        $borrowed = CarbonImmutable::parse($input['borrowed_at'], 'Asia/Jakarta')->utc();
        $due = CarbonImmutable::parse($input['due_at'], 'Asia/Jakarta');
        if (strlen($input['due_at']) === 10) {
            $due = $due->endOfDay();
        }
        $due = $due->utc();
        if ($borrowed->isFuture() || $due->lt($borrowed)) {
            throw ValidationException::withMessages(['borrowed_at' => 'Waktu pinjam harus aktual; batas kembali tidak boleh lebih awal.']);
        }
        $data = ['format' => 'checkout-v1'];
        foreach (['borrower_name', 'student_number', 'study_program', 'whatsapp_number', 'purpose', 'notes'] as $field) {
            $data[$field] = $input[$field] === '' ? null : $input[$field];
        }
        $data['borrowed_at'] = $borrowed->format('Y-m-d H:i:s.u');
        $data['due_at'] = $due->format('Y-m-d H:i:s.u');
        $data['items'] = array_map(fn ($i) => ['unit_id' => (int) $i['unit_id'], 'version' => (int) $i['version'], 'checkout_notes' => ($text($i['checkout_notes'] ?? null) === '' ? null : $text($i['checkout_notes'] ?? null))], $input['items']);
        usort($data['items'], fn ($a, $b) => $a['unit_id'] <=> $b['unit_id']);

        return $data;
    }
}
