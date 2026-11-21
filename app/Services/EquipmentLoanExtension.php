<?php

namespace App\Services;

use App\Exceptions\EquipmentOperationException;
use App\Models\EquipmentAuditEvent;
use App\Models\EquipmentLoan;
use App\Models\User;
use App\Support\EquipmentErrors;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class EquipmentLoanExtension
{
    private function authorize(User $actor, EquipmentLoan $loan): User
    {
        $fresh = User::with('role')->useWritePdo()->findOrFail($actor->id);
        Gate::forUser($fresh)->authorize('extend', $loan);

        return $fresh;
    }

    public function record(User $actor, string $token, array $input): EquipmentAuditEvent
    {
        return EquipmentErrors::run(fn () => $this->performRecord($actor, $token, $input));
    }

    private function performRecord(User $actor, string $token, array $input): EquipmentAuditEvent
    {
        if (DB::transactionLevel() !== 0) {
            throw new \LogicException('Extension requires its own transaction.');
        }
        $token = strtolower($token);
        Validator::make(['token' => $token], ['token' => ['required', 'uuid']])->validate();
        $data = $this->canonicalPayload($input);
        $actor = $this->authorize($actor, EquipmentLoan::useWritePdo()->findOrFail($data['loan_id']));
        $key = 'users:'.$actor->id;
        $hash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        try {
            return DB::transaction(function () use ($actor, $token, $data, $key, $hash): EquipmentAuditEvent {
                // First acquire the header: later snapshot reads include the
                // preceding holder's committed receipt, return or loss records.
                $loan = EquipmentLoan::lockForUpdate()->findOrFail($data['loan_id']);
                $actor = $this->authorize($actor, $loan);
                if ($receipt = $this->replay($token, $key, $hash, $loan->id)) {
                    return $receipt;
                }
                if ($loan->version !== $data['expected_version']) {
                    throw EquipmentOperationException::stale('operation');
                }
                // Every loan lifecycle writer holds this same header first;
                // extension does not need or acquire any unit/detail locks.
                $openItems = $loan->items()->whereNull('returned_at')->orderBy('id')->get(['id', 'lost_at']);
                $now = CarbonImmutable::now('UTC');
                $newDueAt = CarbonImmutable::parse($data['new_due_at'], 'UTC');
                $this->validateState($loan, $openItems->count(), $openItems->whereNotNull('lost_at')->isNotEmpty(), $newDueAt, $now);

                $oldDueAt = $loan->due_at;
                $overdueSeconds = max(0, (int) floor($oldDueAt->diffInSeconds($now, false)));
                $before = [
                    'due_at' => $oldDueAt->format('Y-m-d H:i:s.u'),
                    'initial_due_at' => $loan->initial_due_at->format('Y-m-d H:i:s.u'),
                    'version' => $loan->version,
                ];
                $loan->due_at = $newDueAt;
                $loan->version++;
                $loan->updated_at = $now;
                $loan->save();

                $receipt = new EquipmentAuditEvent;
                $receipt->loan_id = $loan->id;
                $receipt->event_type = 'extended';
                $receipt->operation_token = $token;
                $receipt->operation_actor_key = $key;
                $receipt->operation_payload_hash = $hash;
                $receipt->actor_id = $actor->id;
                $receipt->actor_name = $actor->name;
                $receipt->reason = $data['reason'];
                $receipt->before_data = $before;
                $receipt->after_data = [
                    'due_at' => $data['new_due_at'], 'version' => $loan->version,
                    'extended_at' => $now->format('Y-m-d H:i:s.u'),
                    'open_item_ids' => $openItems->pluck('id')->all(),
                    'was_overdue' => $oldDueAt->lt($now),
                    'overdue_seconds_at_extension' => $overdueSeconds,
                ];
                $receipt->created_at = $now;
                $receipt->save();

                // initial_due_at, returned items' due_at_on_return, and every
                // previous receipt remain untouched.
                return $receipt;
            }, 3);
        } catch (QueryException $exception) {
            // Across loans, UNIQUE operation_token chooses a winner. Read only
            // after the whole failed transaction/snapshot has rolled back.
            if (($exception->errorInfo[1] ?? null) === 1062) {
                $this->authorize($actor, EquipmentLoan::useWritePdo()->findOrFail($data['loan_id']));
                if ($receipt = $this->replay($token, $key, $hash, $data['loan_id'])) {
                    return $receipt;
                }
                throw EquipmentOperationException::duplicate('operation');
            }
            if (in_array($exception->errorInfo[1] ?? null, [1205, 1213], true)) {
                throw EquipmentOperationException::concurrency('operation');
            }
            throw EquipmentErrors::sanitize($exception);
        }
    }

    private function validateState(EquipmentLoan $loan, int $openCount, bool $hasUnresolvedLoss, CarbonImmutable $newDueAt, CarbonImmutable $now): void
    {
        if ($openCount === 0 || $hasUnresolvedLoss) {
            $this->conflict('Perpanjangan memerlukan unit belum kembali dan tidak boleh ada kehilangan yang belum terselesaikan.');
        }
        if (! $newDueAt->gt($loan->due_at) || ! $newDueAt->gt($now)) {
            throw ValidationException::withMessages(['new_due_at' => 'Batas baru harus lebih besar dari batas sebelumnya dan waktu sekarang.']);
        }
    }

    private function replay(string $token, string $key, string $hash, int $loanId): ?EquipmentAuditEvent
    {
        $receipt = EquipmentAuditEvent::useWritePdo()->where('operation_token', $token)->first();
        if (! $receipt) {
            return null;
        }
        if ($receipt->event_type !== 'extended' || (int) $receipt->loan_id !== $loanId
            || $receipt->operation_actor_key !== $key
            || ! is_string($receipt->operation_payload_hash)
            || ! hash_equals($receipt->operation_payload_hash, $hash)) {
            $this->conflict('Token sudah digunakan untuk transaksi, petugas, atau data berbeda.');
        }

        return $receipt;
    }

    private function conflict(string $message): never
    {
        throw ValidationException::withMessages(['operation' => $message]);
    }

    public function canonicalPayload(array $input): array
    {
        if (array_diff(array_keys($input), ['loan_id', 'expected_version', 'new_due_at', 'reason'])) {
            $this->conflict('Perpanjangan hanya menerima jatuh tempo baru dan alasan.');
        }
        if (is_string($input['reason'] ?? null)) {
            $input['reason'] = trim(str_replace(["\r\n", "\r"], "\n", $input['reason']));
        }
        Validator::make($input, [
            'loan_id' => ['required', 'integer', 'min:1'],
            'expected_version' => ['required', 'integer', 'min:1'],
            'new_due_at' => ['required', 'date_format:Y-m-d,Y-m-d\TH:i,Y-m-d\TH:i:s'],
            'reason' => ['required', 'string', 'max:1000'],
        ])->validate();
        $date = CarbonImmutable::parse($input['new_due_at'], 'Asia/Jakarta');
        if (strlen($input['new_due_at']) === 10) {
            $date = $date->endOfDay();
        }
        $date = $date->utc();

        // Hash the initial request, never the header's mutable due_at/version.
        return [
            'format' => 'extension-v1', 'loan_id' => (int) $input['loan_id'],
            'expected_version' => (int) $input['expected_version'],
            'new_due_at' => $date->format('Y-m-d H:i:s.u'), 'reason' => $input['reason'],
        ];
    }
}
