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

class EquipmentLoanCorrection
{
    // Purpose, dates, items, snapshots and receipt metadata are deliberately absent.
    private const FIELDS = ['borrower_name', 'student_number', 'study_program', 'whatsapp_number', 'notes'];

    private function authorize(User $actor, EquipmentLoan $loan): User
    {
        $fresh = User::with('role')->useWritePdo()->findOrFail($actor->id);
        Gate::forUser($fresh)->authorize('correct', $loan);

        return $fresh;
    }

    public function record(User $actor, string $token, array $input): EquipmentAuditEvent
    {
        return EquipmentErrors::run(fn () => $this->performRecord($actor, $token, $input));
    }

    private function performRecord(User $actor, string $token, array $input): EquipmentAuditEvent
    {
        if (DB::transactionLevel() !== 0) {
            throw new \LogicException('Correction requires its own transaction.');
        }
        $token = strtolower($token);
        Validator::make(['token' => $token], ['token' => ['required', 'uuid']])->validate();
        $data = $this->canonicalPayload($input);
        $actor = $this->authorize($actor, EquipmentLoan::useWritePdo()->findOrFail($data['loan_id']));
        $key = 'users:'.$actor->id;
        $hash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        try {
            return DB::transaction(function () use ($actor, $token, $data, $key, $hash): EquipmentAuditEvent {
                // Header first, then receipt: an exact retry remains valid even
                // after subsequent returns, extensions or corrections.
                $loan = EquipmentLoan::lockForUpdate()->findOrFail($data['loan_id']);
                $actor = $this->authorize($actor, $loan);
                if ($receipt = $this->replay($token, $key, $hash, $loan->id)) {
                    return $receipt;
                }
                if ($loan->version !== $data['expected_version']) {
                    throw EquipmentOperationException::stale('operation');
                }
                $before = $loan->only([...self::FIELDS, 'version']);
                foreach (self::FIELDS as $field) {
                    $loan->{$field} = $data[$field];
                }
                if (! $loan->isDirty(self::FIELDS)) {
                    $this->conflict('Tidak ada perubahan identitas atau catatan untuk disimpan.');
                }
                $now = CarbonImmutable::now('UTC');
                $loan->version++;
                $loan->updated_at = $now;
                $loan->save();

                $receipt = new EquipmentAuditEvent;
                $receipt->loan_id = $loan->id;
                $receipt->event_type = 'corrected';
                $receipt->operation_token = $token;
                $receipt->operation_actor_key = $key;
                $receipt->operation_payload_hash = $hash;
                $receipt->actor_id = $actor->id;
                $receipt->actor_name = $actor->name;
                $receipt->reason = $data['reason'];
                $receipt->before_data = $before;
                $receipt->after_data = $loan->only([...self::FIELDS, 'version']);
                $receipt->created_at = $now;
                $receipt->save();

                return $receipt;
            }, 3);
        } catch (QueryException $exception) {
            // Cross-loan/type token collisions must roll back every header write
            // before looking up and comparing the winner in a fresh read.
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

    private function replay(string $token, string $key, string $hash, int $loanId): ?EquipmentAuditEvent
    {
        $receipt = EquipmentAuditEvent::useWritePdo()->where('operation_token', $token)->first();
        if (! $receipt) {
            return null;
        }
        if ($receipt->event_type !== 'corrected' || (int) $receipt->loan_id !== $loanId
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
        if (array_diff(array_keys($input), [...self::FIELDS, 'loan_id', 'expected_version', 'reason'])) {
            $this->conflict('Koreksi hanya menerima identitas peminjam, catatan, dan alasan.');
        }
        foreach ([...self::FIELDS, 'reason'] as $field) {
            if (is_string($input[$field] ?? null)) {
                $input[$field] = trim(str_replace(["\r\n", "\r"], "\n", $input[$field]));
            }
        }
        if (is_string($input['whatsapp_number'] ?? null)) {
            $input['whatsapp_number'] = preg_replace('/[\s()+-]/', '', $input['whatsapp_number']);
            if (str_starts_with($input['whatsapp_number'], '08')) {
                $input['whatsapp_number'] = '62'.substr($input['whatsapp_number'], 1);
            }
        }
        Validator::make($input, [
            'loan_id' => ['required', 'integer', 'min:1'],
            'expected_version' => ['required', 'integer', 'min:1'],
            'borrower_name' => ['required', 'string', 'max:150'],
            'student_number' => ['required', 'string', 'max:30', 'regex:/\A[A-Za-z0-9.-]+\z/'],
            'study_program' => ['required', 'string', 'max:150'],
            'whatsapp_number' => ['required', 'string', 'regex:/\A[1-9][0-9]{7,14}\z/'],
            'notes' => ['present', 'nullable', 'string', 'max:5000'],
            'reason' => ['required', 'string', 'max:1000'],
        ])->validate();
        $data = [
            'format' => 'correction-v1', 'loan_id' => (int) $input['loan_id'],
            'expected_version' => (int) $input['expected_version'],
        ];
        foreach (self::FIELDS as $field) {
            $data[$field] = $input[$field] === '' ? null : $input[$field];
        }
        $data['reason'] = $input['reason'];

        return $data;
    }
}
