<?php

namespace App\Services\Equipment;

use App\Models\User;
use App\Support\EquipmentPermissions;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class LoanService
{
    public const OPERATIONS = ['loan.checkout', 'return.create', 'loan.extend', 'loan.void', 'loan.loss'];

    public function execute(User $user, string $op, string $token, array $input, array $uploads = []): int
    {
        abort_unless(in_array($op, self::OPERATIONS, true), 403);
        $this->actor($user, $op);
        Validator::make(['token' => $token], ['token' => 'required|uuid'])->validate();
        $data = $this->validate($op, $input);
        $paths = [];
        try {
            $photos = $this->preparePhotos($op, $data, $uploads, $paths);
            $manifest = [];
            foreach ($photos as $id => $files) {
                $manifest[$id] = array_column($files, 'sha256');
            }
            $hash = hash('sha256', json_encode(LoanRules::canonical(['operation' => $op, 'input' => $data, 'photos' => $manifest]), JSON_THROW_ON_ERROR));
            $key = DB::table('equipment_operation_keys')->where('token', $token)->first();
            if ($key) {
                return $this->replay($key, $user, $op, $hash);
            }
            try {
                return DB::transaction(function () use ($user, $op, $token, $data, $photos, $hash) {
                    $actor = $this->actor($user, $op);
                    $claimTime = now('UTC')->format('Y-m-d H:i:s.u');
                    DB::table('equipment_operation_keys')->insert([
                        'token' => $token, 'actor_id' => $actor->id, 'actor_key' => 'users:'.$actor->id,
                        'operation' => $op, 'payload_hash' => $hash, 'created_at' => $claimTime,
                    ]);
                    if ($op === 'loan.checkout') {
                        [$loanId, $resultId, $before, $after, $reason, $now] = $this->checkout($actor, $data);
                    } else {
                        [$loanId, $resultId, $before, $after, $reason, $now] = $this->change($actor, $op, $data, $photos);
                    }
                    $event = match ($op) {
                        'loan.checkout' => 'loan.checked_out', 'return.create' => 'return.created',
                        'loan.extend' => 'loan.extended', 'loan.void' => 'loan.voided', 'loan.loss' => 'loan.loss_recorded',
                    };
                    $type = $op === 'return.create' ? 'return' : 'loan';
                    DB::table('equipment_audit_log')->insert([
                        'actor_id' => $actor->id, 'actor_name_snapshot' => $actor->name ?: $actor->username,
                        'event_name' => $event, 'subject_type' => $type, 'subject_id' => $resultId, 'loan_id' => $loanId,
                        'reason' => $reason, 'before_data' => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR),
                        'after_data' => json_encode($after + ['operation_token' => $token], JSON_THROW_ON_ERROR),
                        'occurred_at' => $now, 'created_at' => $now,
                    ]);
                    DB::table('equipment_operation_keys')->where('token', $token)->update([
                        'result_type' => $type, 'result_id' => $resultId, 'completed_at' => $now,
                    ]);

                    return $resultId;
                }, 3);
            } catch (QueryException $e) {
                if (($e->errorInfo[1] ?? null) === 1062) {
                    $key = DB::table('equipment_operation_keys')->where('token', $token)->first();
                    if ($key) {
                        return $this->replay($key, $user, $op, $hash);
                    }
                    $this->fail('units', 'Nomor transaksi atau unit aktif sudah digunakan. Muat ulang pilihan unit.');
                }
                throw $e;
            }
        } finally {
            // Only this attempt's random objects; never purge historical evidence or another request's file.
            foreach ($paths as $path) {
                try {
                    $used = false;
                    foreach (['equipment_model_photos', 'equipment_unit_photos', 'equipment_return_photos'] as $table) {
                        $used = $used || DB::table($table)->where('storage_path', $path)->exists();
                    }
                    if (! $used) {
                        Storage::disk('local')->delete($path);
                    }
                } catch (\Throwable) {
                    Log::warning('Equipment return attempt file needs reconciliation.');
                }
            }
        }
    }

    private function actor(User $user, string $op): User
    {
        $actor = User::with('role')->findOrFail($user->id);
        Gate::forUser($actor)->authorize(EquipmentPermissions::OPERATIONS[$op]);

        return $actor;
    }

    private function replay(object $key, User $user, string $op, string $hash): int
    {
        $actor = $this->actor($user, $op);
        if ((int) $key->actor_id !== (int) $actor->id || $key->actor_key !== 'users:'.$actor->id || $key->operation !== $op || ! hash_equals($key->payload_hash, $hash)) {
            $this->fail('action', 'Permintaan sudah berubah. Buka kembali formulir.');
        }
        abort_unless($key->completed_at && $key->result_id && $key->result_type, 503, 'Operasi belum lengkap; hubungi administrator.');

        return (int) $key->result_id;
    }

    private function validate(string $op, array $input): array
    {
        $trim = function (&$value) {
            if (is_string($value)) {
                $value = trim(str_replace("\r\n", "\n", $value));
            }
        };
        array_walk_recursive($input, $trim);
        if ($op === 'loan.checkout') {
            $rules = [
                'borrower_type' => ['required', Rule::in(['student', 'lecturer', 'staff'])],
                'borrower_name' => 'required|string|max:150', 'identity_number' => 'required|string|max:40',
                'borrower_contact' => 'required|string|max:100', 'borrower_affiliation' => 'required|string|max:150',
                'purpose' => 'required|string|max:5000', 'due_date' => 'required|date_format:Y-m-d',
                'units' => 'required|array|min:1|max:50', 'units.*' => 'required|integer|min:1|distinct',
            ];
        } else {
            $rules = ['loan_id' => 'required|integer|min:1', 'version' => 'required|integer|min:1'];
            if ($op === 'loan.extend') {
                $rules += ['due_date' => 'required|date_format:Y-m-d'];
            }
            if (in_array($op, ['loan.extend', 'loan.loss', 'loan.void'], true)) {
                $rules['reason'] = 'required|string|max:1000';
            }
            if ($op === 'loan.void') {
                $rules['not_handed_over'] = 'required|accepted';
            }
            if (in_array($op, ['return.create', 'loan.loss'], true)) {
                $rules += [
                    'items' => 'required|array|min:1|max:10', 'items.*' => 'required|array:loan_unit_id,condition,notes',
                    'items.*.loan_unit_id' => 'required|integer|min:1|distinct', 'items.*.notes' => 'nullable|string|max:1000',
                ];
                if ($op === 'return.create') {
                    $rules += ['notes' => 'nullable|string|max:1000', 'items.*.condition' => ['required', Rule::in(['good', 'minor_damage', 'major_damage'])]];
                }
            }
        }
        $data = EquipmentValidation::validate($input, $rules);
        foreach (['loan_id', 'version'] as $key) {
            if (isset($data[$key])) {
                $data[$key] = (int) $data[$key];
            }
        }
        if (isset($data['units'])) {
            $data['units'] = array_map('intval', $data['units']);
            sort($data['units']);
        }
        if (isset($data['items'])) {
            foreach ($data['items'] as &$item) {
                $item['loan_unit_id'] = (int) $item['loan_unit_id'];
                $item['notes'] = $item['notes'] ?? '';
            }
            unset($item);
            usort($data['items'], fn ($a, $b) => $a['loan_unit_id'] <=> $b['loan_unit_id']);
        }
        if (isset($data['not_handed_over'])) {
            $data['not_handed_over'] = true;
        }

        return $data;
    }

    private function preparePhotos(string $op, array $data, array $uploads, array &$paths): array
    {
        if ($op !== 'return.create') {
            if ($uploads) {
                $this->fail('photos', 'Foto hanya diterima pada operasi pengembalian.');
            }

            return [];
        }
        $expected = array_column($data['items'], 'loan_unit_id');
        $actual = array_map('intval', array_keys($uploads));
        sort($actual);
        foreach (array_diff($expected, $actual) as $id) {
            $this->fail('photos.'.$id, 'Unggah minimal satu foto untuk unit ini.');
        }
        if ($actual !== $expected) {
            $this->fail('photos', 'Setiap unit terpilih wajib memiliki foto sendiri; foto unit lain tidak diterima.');
        }
        EquipmentValidation::validate(['photos' => $uploads], ['photos' => 'array|max:10', 'photos.*' => 'required|array|min:1|max:8', 'photos.*.*' => 'required|file|max:4883']);
        $count = array_sum(array_map('count', $uploads));
        $inputBytes = 0;
        foreach ($uploads as $files) {
            foreach ($files as $file) {
                $inputBytes += $file->getSize();
            }
        }
        if ($count > 40 || $inputBytes > 100000000) {
            $this->fail('photos', 'Unggah maksimal 40 foto dengan total maksimal 100 MB.');
        }
        $prepared = [];
        $outputBytes = 0;
        foreach ($expected as $id) {
            foreach ($uploads[$id] as $file) {
                try {
                    $photo = app(InventoryPhotos::class)->prepare($file);
                } catch (ValidationException $error) {
                    $this->fail('photos.'.$id, $error->validator->errors()->first());
                }
                $outputBytes += $photo['file_size'];
                if ($outputBytes > 100000000) {
                    $this->fail('photos', 'Total foto setelah diproses melebihi 100 MB.');
                }
                $metadata = app(InventoryPhotos::class)->store($photo);
                $paths[] = $metadata['storage_path'];
                $prepared[$id][] = $metadata;
                unset($photo);
            }
        }

        return $prepared;
    }

    private function checkout(User $actor, array $d): array
    {
        $units = $this->lockUnits($d['units'], true);
        if (count(array_unique(array_column($units, 'model_id'))) > 20) {
            $this->fail('units', 'Pilih unit dari maksimal 20 jenis peralatan.');
        }
        $event = now('UTC');
        if ($d['due_date'] < $event->copy()->timezone('Asia/Jakarta')->toDateString()) {
            $this->fail('due_date', 'Jatuh tempo minimal hari ini (WIB).');
        }
        $now = $event->format('Y-m-d H:i:s.u');
        $header = array_intersect_key($d, array_flip(['borrower_type', 'borrower_name', 'identity_number', 'borrower_contact', 'borrower_affiliation', 'purpose', 'due_date']));
        $header += ['loan_number' => 'EQ-L-'.strtoupper((string) Str::ulid()), 'borrowed_at' => $now, 'created_by' => $actor->id, 'version' => 1, 'created_at' => $now, 'updated_at' => $now];
        $id = DB::table('equipment_loans')->insertGetId($header);
        $snapshots = [];
        foreach ($units as $unit) {
            if (! $unit->model_active || $unit->operational_status !== 'active' || $unit->physical_condition !== 'good'
                || DB::table('equipment_loan_units')->where('unit_id', $unit->id)->where('outcome', 'on_loan')->exists()) {
                $this->fail('units', 'Salah satu unit tidak lagi tersedia. Muat ulang pilihan.');
            }
            $loanUnitId = DB::table('equipment_loan_units')->insertGetId([
                'loan_id' => $id, 'unit_id' => $unit->id, 'category_id_at_checkout' => $unit->category_id,
                'model_id_at_checkout' => $unit->model_id, 'condition_out' => $unit->physical_condition,
                'outcome' => 'on_loan', 'created_at' => $now, 'updated_at' => $now,
            ]);
            $snapshots[] = ['loan_unit_id' => $loanUnitId, 'unit_id' => $unit->id, 'asset_code' => $unit->asset_code,
                'serial_number' => $unit->serial_number, 'model_id' => $unit->model_id, 'model_name' => $unit->model_name,
                'category_id' => $unit->category_id, 'category_name' => $unit->category_name, 'condition_out' => $unit->physical_condition];
            DB::table('equipment_units')->where('id', $unit->id)->update(['version' => $unit->version + 1, 'updated_at' => $now]);
        }

        return [$id, $id, null, ['loan' => $header + ['id' => $id], 'units' => $snapshots], 'Pendaftaran peminjaman peralatan', $now];
    }

    private function change(User $actor, string $op, array $d, array $photos): array
    {
        $loan = DB::table('equipment_loans')->where('id', $d['loan_id'])->lockForUpdate()->first();
        abort_unless($loan, 404);
        if ((int) $loan->version !== $d['version']) {
            $this->fail('action', 'Transaksi berubah sejak dibuka; muat ulang detail/formulir.');
        }
        $all = DB::table('equipment_loan_units')->where('loan_id', $loan->id)->orderBy('id')->get();
        $progress = LoanRules::progress($all->pluck('outcome')->all());
        if (! in_array($progress, ['active', 'partially_resolved'], true)) {
            $this->fail('action', 'Tindakan ini tidak tersedia untuk transaksi ini.');
        }
        $before = ['loan' => (array) $loan];
        $receipt = null;
        $now = null;
        if ($op === 'loan.extend') {
            $event = now('UTC');
            if ($d['due_date'] <= $loan->due_date || $d['due_date'] < $event->copy()->timezone('Asia/Jakarta')->toDateString()) {
                $this->fail('due_date', 'Tanggal baru harus lebih maju dari jatuh tempo sebelumnya dan tidak di masa lalu WIB.');
            }
            $now = $event->format('Y-m-d H:i:s.u');
            DB::table('equipment_loans')->where('id', $loan->id)->update(['due_date' => $d['due_date']]);
            $changed = [];
        } else {
            if ($op === 'loan.void') {
                if ($progress !== 'active') {
                    $this->fail('action', 'Peminjaman hanya dapat dibatalkan sebelum serah terima dan sebelum ada unit yang diselesaikan.');
                }
                $selected = $all;
                $byId = [];
            } else {
                $ids = array_column($d['items'], 'loan_unit_id');
                $selected = $all->whereIn('id', $ids)->values();
                if ($selected->count() !== count($ids) || $selected->contains(fn ($row) => $row->outcome !== 'on_loan')) {
                    $this->fail('items', 'Unit harus milik transaksi ini dan belum pernah diselesaikan.');
                }
                $byId = array_column($d['items'], null, 'loan_unit_id');
            }
            $units = $this->lockUnits($selected->pluck('unit_id')->all());
            $physical = array_column($units, null, 'id');
            // All writers honor the header/model/unit order; lock detail rows only after physical units.
            $locked = [];
            foreach ($selected as $row) {
                $locked[$row->id] = DB::table('equipment_loan_units')->where('id', $row->id)->lockForUpdate()->first();
                if ($locked[$row->id]->outcome !== 'on_loan' || $physical[$row->unit_id]->operational_status !== 'active') {
                    $this->fail('items', 'Status unit telah berubah. Buka kembali formulir.');
                }
            }
            $event = now('UTC');
            $now = $event->format('Y-m-d H:i:s.u');
            $late = LoanRules::late($loan->due_date, $event);
            if ($op === 'return.create') {
                foreach ($selected as $row) {
                    $item = $byId[$row->id];
                    if (($late || $item['condition'] !== 'good') && ! $item['notes']) {
                        $this->fail('unitNotes.'.$row->id, 'Isi catatan untuk unit yang rusak atau terlambat dikembalikan.');
                    }
                }
                $receipt = DB::table('equipment_returns')->insertGetId(['loan_id' => $loan->id, 'returned_at' => $now, 'received_by' => $actor->id, 'notes' => $d['notes'] ?? null, 'created_at' => $now]);
            }
            $changed = [];
            foreach ($selected as $row) {
                $unit = $physical[$row->unit_id];
                $item = $byId[$row->id] ?? [];
                $outcome = match ($op) {
                    'return.create' => 'returned', 'loan.loss' => 'lost', 'loan.void' => 'voided'
                };
                $notes = $op === 'return.create' ? ($item['notes'] ?: null) : $d['reason'];
                $resolution = ['outcome' => $outcome, 'resolution_at' => $now, 'resolution_notes' => $notes, 'updated_at' => $now,
                    'return_id' => $receipt, 'condition_in' => $op === 'return.create' ? $item['condition'] : null,
                    'due_date_at_resolution' => $op === 'loan.void' ? null : $loan->due_date,
                    'is_late' => $op === 'loan.void' ? null : (int) $late];
                DB::table('equipment_loan_units')->where('id', $row->id)->where('outcome', 'on_loan')->update($resolution);
                $physicalChanges = ['version' => $unit->version + 1, 'updated_at' => $now];
                if ($op === 'return.create') {
                    $physicalChanges += ['physical_condition' => $item['condition'], 'condition_notes' => $item['notes'] ?: null];
                }
                if ($op === 'loan.loss') {
                    $physicalChanges += ['operational_status' => 'lost', 'status_reason' => $d['reason'], 'closed_at' => $now, 'closed_reason' => $d['reason']];
                }
                DB::table('equipment_units')->where('id', $unit->id)->update($physicalChanges);
                $photoIds = [];
                foreach ($photos[$row->id] ?? [] as $metadata) {
                    $photoIds[] = DB::table('equipment_return_photos')->insertGetId($metadata + ['return_id' => $receipt, 'loan_unit_id' => $row->id, 'uploaded_by' => $actor->id, 'created_at' => $now]);
                }
                $changed[] = ['before' => (array) $row, 'after' => $resolution, 'unit_before' => (array) $unit, 'unit_after' => $physicalChanges, 'photo_ids' => $photoIds];
                if ($op === 'loan.loss') {
                    DB::table('equipment_audit_log')->insert(['actor_id' => $actor->id, 'actor_name_snapshot' => $actor->name ?: $actor->username,
                        'event_name' => 'unit.lost', 'subject_type' => 'unit', 'subject_id' => $unit->id, 'loan_id' => $loan->id,
                        'reason' => $d['reason'], 'before_data' => json_encode((array) $unit, JSON_THROW_ON_ERROR), 'after_data' => json_encode($physicalChanges, JSON_THROW_ON_ERROR),
                        'occurred_at' => $now, 'created_at' => $now]);
                }
            }
        }
        DB::table('equipment_loans')->where('id', $loan->id)->update(['version' => $loan->version + 1, 'updated_at' => $now]);
        $after = ['loan' => (array) DB::table('equipment_loans')->where('id', $loan->id)->first(), 'units' => $changed];
        if ($op === 'loan.void') {
            $after['attestation_not_handed_over'] = true;
        }

        return [$loan->id, $receipt ?? $loan->id, $before, $after, $op === 'return.create' ? 'Pengembalian peralatan; kondisi dan catatan per unit tercatat' : $d['reason'], $now];
    }

    private function lockUnits(array $ids, bool $checkout = false): array
    {
        sort($ids);
        $pre = DB::table('equipment_units as u')->join('equipment_models as m', 'm.id', '=', 'u.model_id')
            ->whereIn('u.id', $ids)->select('u.id', 'u.model_id', 'm.category_id')->get();
        if ($pre->count() !== count($ids)) {
            $this->fail('units', 'Salah satu unit tidak ditemukan.');
        }
        $categoryIds = $pre->pluck('category_id')->unique()->sort()->values();
        if ($checkout) {
            foreach ($categoryIds as $id) {
                DB::table('equipment_categories')->where('id', $id)->lockForUpdate()->first();
            }
        }
        $models = [];
        foreach ($pre->pluck('model_id')->unique()->sort() as $id) {
            $models[$id] = DB::table('equipment_models')->where('id', $id)->lockForUpdate()->first();
            if ($checkout && ! $categoryIds->contains($models[$id]->category_id)) {
                $this->fail('units', 'Kategori model berubah; muat ulang pilihan.');
            }
        }
        $units = [];
        foreach ($ids as $id) {
            $unit = DB::table('equipment_units')->where('id', $id)->lockForUpdate()->first();
            $model = $models[$unit->model_id] ?? null;
            if (! $model) {
                $this->fail('units', 'Model unit berubah; muat ulang.');
            }
            $unit->category_id = $model->category_id;
            $unit->model_name = $model->name;
            $unit->model_active = (bool) $model->is_active;
            $unit->category_name = $checkout ? DB::table('equipment_categories')->where('id', $model->category_id)->value('name') : null;
            $units[] = $unit;
        }

        return $units;
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    public function listing()
    {
        $totals = DB::table('equipment_loan_units')->select('loan_id')->selectRaw('COUNT(*) as total_units, SUM(outcome=\'on_loan\') as open_units')
            ->selectRaw(LoanRules::progressSql().' as progress')->groupBy('loan_id');

        return DB::table('equipment_loans as l')->leftJoinSub($totals, 'p', 'p.loan_id', '=', 'l.id')
            ->select('l.*')->selectRaw("COALESCE(p.total_units,0) as total_units, COALESCE(p.open_units,0) as open_units, COALESCE(p.progress,'integrity_error') as progress")
            ->selectRaw('(COALESCE(p.open_units,0)>0 AND l.due_date < ?) as overdue', [now('Asia/Jakarta')->toDateString()]);
    }

    public function detail(int $id): array
    {
        $loan = $this->listing()->where('l.id', $id)->first();
        abort_unless($loan, 404);
        $units = DB::table('equipment_loan_units')->where('loan_id', $id)->orderBy('id')->get();
        $audit = DB::table('equipment_audit_log')->where('loan_id', $id)->orderBy('id')->get();
        $checkout = $audit->firstWhere('event_name', 'loan.checked_out');
        $snapshot = $checkout ? json_decode($checkout->after_data, true, 512, JSON_THROW_ON_ERROR) : [];
        $byId = array_column($snapshot['units'] ?? [], null, 'loan_unit_id');
        foreach ($units as $row) {
            $row->snapshot = $byId[$row->id] ?? null;
        }

        return ['loan' => $loan, 'loanUnits' => $units, 'receipts' => DB::table('equipment_returns')->where('loan_id', $id)->orderByDesc('id')->get(),
            'evidence' => DB::table('equipment_return_photos as p')->join('equipment_returns as r', 'r.id', '=', 'p.return_id')->where('r.loan_id', $id)->select('p.*')->orderBy('p.id')->get(),
            'audit' => $audit, 'originalDue' => $snapshot['loan']['due_date'] ?? null];
    }
}
