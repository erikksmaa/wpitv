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
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class InventoryService
{
    private array $writtenPaths = [];

    public function execute(User $user, string $operation, string $token, array $input, array $photos = []): int
    {
        abort_unless(isset(EquipmentPermissions::OPERATIONS[$operation]), 403);
        abort_if(in_array($operation, LoanService::OPERATIONS, true), 403);
        $actor = User::with('role')->findOrFail($user->id);
        Gate::forUser($actor)->authorize(EquipmentPermissions::OPERATIONS[$operation]);
        Validator::make(['token' => $token], ['token' => 'required|uuid'])->validate();
        $data = $this->validate($operation, $input);
        $hashData = $data;
        ksort($hashData);
        $hash = hash('sha256', json_encode([$operation, $hashData, array_column($photos, 'sha256')], JSON_THROW_ON_ERROR));
        $key = DB::table('equipment_operation_keys')->where('token', $token)->first();
        if ($key) {
            return $this->replay($key, $actor, $operation, $hash);
        }
        $this->writtenPaths = [];
        try {
            return DB::transaction(function () use ($actor, $operation, $token, $hash, $data, $photos) {
                // Recheck current authorization in the same transaction as the write.
                $current = User::with('role')->findOrFail($actor->id);
                Gate::forUser($current)->authorize(EquipmentPermissions::OPERATIONS[$operation]);
                $now = now('UTC')->format('Y-m-d H:i:s.u');
                DB::table('equipment_operation_keys')->insert([
                    'token' => $token, 'actor_id' => $actor->id, 'actor_key' => 'users:'.$actor->id,
                    'operation' => $operation, 'payload_hash' => $hash, 'created_at' => $now,
                ]);
                [$type, $id, $before, $after, $reason] = $this->mutate($operation, $data, $photos, $current, $now);
                DB::table('equipment_audit_log')->insert([
                    'actor_id' => $current->id, 'actor_name_snapshot' => $current->name ?: $current->username,
                    'event_name' => $this->eventName($operation), 'subject_type' => $type, 'subject_id' => $id,
                    'reason' => $reason, 'before_data' => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR),
                    'after_data' => json_encode(['record' => $after, 'operation_token' => $token], JSON_THROW_ON_ERROR),
                    'occurred_at' => $now, 'created_at' => $now,
                ]);
                $resultType = match ($operation) {
                    'model.photo_add' => 'model_photo',
                    'unit.photo_add' => 'unit_photo',
                    default => $type,
                };
                $resultId = $after['photo_ids'][0] ?? $id;
                DB::table('equipment_operation_keys')->where('token', $token)->update([
                    'result_type' => $resultType, 'result_id' => $resultId, 'completed_at' => $now,
                ]);

                return (int) $resultId;
            }, 3);
        } catch (QueryException $e) {
            // A concurrent duplicate token waits for the first transaction; read afresh after rollback.
            if (($e->errorInfo[1] ?? null) === 1062) {
                $key = DB::table('equipment_operation_keys')->where('token', $token)->first();
                if ($key) {
                    return $this->replay($key, $actor, $operation, $hash);
                }
                throw ValidationException::withMessages(['code' => 'Kode kategori/model/aset sudah digunakan. Muat ulang dan gunakan kode lain.']);
            }
            throw $e;
        } finally {
            $this->cleanupUnreferencedAttemptFiles();
        }
    }

    private function cleanupUnreferencedAttemptFiles(): void
    {
        foreach ($this->writtenPaths as $path) {
            try {
                $referenced = false;
                foreach (['equipment_model_photos', 'equipment_unit_photos', 'equipment_return_photos'] as $table) {
                    $referenced = $referenced || DB::table($table)->where('storage_path', $path)->exists();
                }
                if (! $referenced) {
                    Storage::disk('local')->delete($path);
                }
            } catch (\Throwable) {
                // Keep the private object if ownership cannot be proven after a DB/storage failure.
                Log::warning('Equipment attempt file needs reconciliation.');
            }
        }
    }

    private function eventName(string $operation): string
    {
        return match ($operation) {
            'category.create' => 'category.created', 'category.update' => 'category.updated',
            'model.create' => 'model.created', 'model.update' => 'model.updated',
            'stock.add' => 'stock.added', 'unit.condition' => 'unit.condition_changed',
            'unit.maintenance_start' => 'unit.maintenance_started', 'unit.maintenance_finish' => 'unit.maintenance_finished',
            'unit.lost' => 'unit.lost', 'unit.found' => 'unit.found', 'unit.retire' => 'unit.retired',
            'model.photo_add' => 'model.photo_added', 'unit.photo_add' => 'unit.photo_added',
        };
    }

    private function replay(object $key, User $actor, string $operation, string $hash): int
    {
        Gate::forUser(User::with('role')->findOrFail($actor->id))->authorize(EquipmentPermissions::OPERATIONS[$operation]);
        if ((int) $key->actor_id !== (int) $actor->id || $key->actor_key !== 'users:'.$actor->id || $key->operation !== $operation || ! hash_equals($key->payload_hash, $hash)) {
            throw ValidationException::withMessages(['action' => 'Token sudah digunakan untuk permintaan berbeda. Buka kembali formulir.']);
        }
        abort_unless($key->completed_at && $key->result_type && $key->result_id, 503, 'Operasi belum lengkap; hubungi administrator.');

        return (int) $key->result_id;
    }

    private function validate(string $operation, array $input): array
    {
        foreach ($input as &$value) {
            if (is_string($value)) {
                $value = trim($value);
            }
        }
        unset($value);
        $update = str_ends_with($operation, '.update');
        $rules = ['reason' => 'nullable|string|max:1000'];
        if (! in_array($operation, ['category.create', 'model.create', 'model.photo_add', 'unit.photo_add'], true)) {
            $rules['reason'] = 'required|string|max:1000';
        }
        if (! in_array($operation, ['category.create', 'model.create'], true)) {
            $rules += ['id' => 'required|integer|min:1', 'version' => 'required|integer|min:1'];
        }
        if (str_starts_with($operation, 'category.') || str_starts_with($operation, 'model.') && ! str_ends_with($operation, 'photo_add')) {
            $rules += ['name' => 'required|string|max:150', 'description' => 'nullable|string|max:10000', 'is_active' => 'required|boolean'];
            if (! $update) {
                $rules['code'] = ['required', 'string', 'max:'.($operation === 'category.create' ? 40 : 60), 'regex:/\A[A-Z0-9]+(?:-[A-Z0-9]+)*\z/'];
            }
        }
        if (in_array($operation, ['model.create', 'model.update'], true)) {
            $rules['category_id'] = 'required|integer|min:1';
        }
        if (in_array($operation, ['model.create', 'stock.add'], true)) {
            $rules['quantity'] = 'required|integer|between:1,100';
            $rules['condition'] = ['required', Rule::in(['good', 'minor_damage', 'major_damage'])];
        }
        if ($operation === 'unit.condition') {
            $rules += ['condition' => ['required', Rule::in(['good', 'minor_damage', 'major_damage'])], 'serial_number' => 'nullable|string|max:150'];
        }

        return Validator::make($input, $rules)->validate();
    }

    private function lock(string $table, int $id, int $version): object
    {
        $row = DB::table($table)->where('id', $id)->lockForUpdate()->first();
        abort_unless($row, 404);
        if ((int) $row->version !== $version) {
            throw ValidationException::withMessages(['action' => 'Data berubah sejak dibuka. Muat ulang formulir sebelum menyimpan.']);
        }

        return $row;
    }

    private function save(string $table, object $row, array $changes, string $now): void
    {
        $different = false;
        foreach ($changes as $key => $value) {
            if (($row->$key ?? null) != $value) {
                $different = true;
            }
        }
        if (! $different) {
            throw ValidationException::withMessages(['action' => 'Tidak ada perubahan untuk disimpan.']);
        }
        DB::table($table)->where('id', $row->id)->update($changes + ['version' => $row->version + 1, 'updated_at' => $now]);
    }

    private function mutate(string $op, array $d, array $photos, User $actor, string $now): array
    {
        $before = null;
        $reason = $d['reason'] ?? '';
        if (str_starts_with($op, 'category.')) {
            $type = 'category';
            $table = 'equipment_categories';
            $changes = ['name' => $d['name'], 'description' => $d['description'] ?: null, 'is_active' => (bool) $d['is_active']];
            if ($op === 'category.create') {
                $id = DB::table($table)->insertGetId($changes + ['code' => $d['code'], 'version' => 1, 'created_at' => $now, 'updated_at' => $now]);
                $reason = $reason ?: 'category.create: pendaftaran kategori';
            } else {
                $row = $this->lock($table, $d['id'], $d['version']);
                $before = (array) $row;
                $id = $row->id;
                $this->save($table, $row, $changes, $now);
            }
        } elseif (in_array($op, ['model.create', 'model.update'], true)) {
            $table = 'equipment_models';
            $type = 'model';
            // Reclassification locks both categories in global ascending order before the model.
            $old = $op === 'model.update' ? DB::table($table)->where('id', $d['id'])->first() : null;
            abort_if($op === 'model.update' && ! $old, 404);
            $ids = array_unique(array_filter([$old?->category_id, $d['category_id']]));
            sort($ids);
            $categories = [];
            foreach ($ids as $categoryId) {
                $categories[$categoryId] = DB::table('equipment_categories')->where('id', $categoryId)->lockForUpdate()->first();
            }
            $category = $categories[$d['category_id']] ?? null;
            abort_unless($category, 404);
            if ((! $old || $old->category_id != $d['category_id']) && ! $category->is_active) {
                throw ValidationException::withMessages(['category_id' => 'Kategori tujuan harus aktif.']);
            }
            $changes = ['category_id' => $d['category_id'], 'name' => $d['name'], 'description' => $d['description'] ?: null, 'is_active' => (bool) $d['is_active']];
            if ($op === 'model.create') {
                $id = DB::table($table)->insertGetId($changes + ['model_code' => $d['code'], 'next_unit_no' => 1, 'version' => 1, 'created_at' => $now, 'updated_at' => $now]);
                $unitIds = $this->addUnits(DB::table($table)->where('id', $id)->first(), $d['quantity'], $d['condition'], $now);
                // A freshly-created model starts at version 1, including its initial units.
                $reason = $reason ?: 'model.create: pendaftaran model dan stok awal';
            } else {
                $row = $this->lock($table, $d['id'], $d['version']);
                if ($row->category_id != $old->category_id) {
                    throw ValidationException::withMessages(['action' => 'Kategori model berubah; muat ulang.']);
                }
                if (! $d['is_active'] && $this->modelBorrowed($row->id)) {
                    throw ValidationException::withMessages(['action' => 'Model dengan unit dipinjam tidak dapat dinonaktifkan.']);
                }
                $before = (array) $row;
                $id = $row->id;
                $this->save($table, $row, $changes, $now);
            }
        } elseif ($op === 'stock.add') {
            $table = 'equipment_models';
            $type = 'model';
            $row = $this->lock($table, $d['id'], $d['version']);
            $before = (array) $row;
            $id = $row->id;
            $unitIds = $this->addUnits($row, $d['quantity'], $d['condition'], $now);
            DB::table($table)->where('id', $id)->update(['version' => $row->version + 1]);
        } elseif (str_ends_with($op, '.photo_add')) {
            $isModel = $op === 'model.photo_add';
            $type = $isModel ? 'model' : 'unit';
            $table = $isModel ? 'equipment_models' : 'equipment_units';
            if (! $isModel) {
                $this->lockUnitModel($d['id']);
            }
            $row = $this->lock($table, $d['id'], $d['version']);
            if (! $isModel && ($row->operational_status === 'retired' || $this->borrowed($row->id))) {
                throw ValidationException::withMessages(['action' => 'Foto unit retired/dipinjam tidak boleh ditambah melalui Inventory.']);
            }
            $photoTable = $isModel ? 'equipment_model_photos' : 'equipment_unit_photos';
            $parent = $isModel ? 'model_id' : 'unit_id';
            if (! $photos || count($photos) > ($isModel ? 1 : 8)) {
                throw ValidationException::withMessages(['photos' => 'Unggah satu foto model atau 1–8 foto unit.']);
            }
            if (DB::table($photoTable)->where($parent, $row->id)->exists() && ! $reason) {
                throw ValidationException::withMessages(['reason' => 'Foto tambahan/revisi membutuhkan alasan.']);
            }
            $before = (array) $row;
            $id = $row->id;
            $photoIds = [];
            foreach ($photos as $photo) {
                $metadata = app(InventoryPhotos::class)->store($photo);
                $this->writtenPaths[] = $metadata['storage_path'];
                $photoIds[] = DB::table($photoTable)->insertGetId($metadata + [$parent => $id, 'uploaded_by' => $actor->id, 'created_at' => $now] + ($isModel ? ['is_cover' => 1] : []));
            }
            DB::table($table)->where('id', $id)->update(['version' => $row->version + 1, 'updated_at' => $now]);
            $reason = $reason ?: $op.': dokumentasi pertama';
        } else {
            $type = 'unit';
            $table = 'equipment_units';
            $this->lockUnitModel($d['id']);
            $row = $this->lock($table, $d['id'], $d['version']);
            $before = (array) $row;
            $id = $row->id;
            $borrowed = $this->borrowed($id);
            if ($op === 'unit.condition') {
                if ($borrowed || ! in_array($row->operational_status, ['active', 'maintenance'], true)) {
                    throw ValidationException::withMessages(['action' => 'Kondisi/serial hanya dapat diubah pada unit bebas active/maintenance.']);
                }
                $changes = ['physical_condition' => $d['condition'], 'serial_number' => $d['serial_number'] ?: null, 'condition_notes' => $reason];
            } else {
                $next = InventoryRules::transition($row->operational_status, $op, $row->physical_condition, $borrowed);
                $closed = in_array($next, ['lost', 'retired'], true);
                $changes = ['operational_status' => $next, 'status_reason' => $reason, 'closed_at' => $closed ? $now : null, 'closed_reason' => $closed ? $reason : null];
            }
            $this->save($table, $row, $changes, $now);
        }
        $after = (array) DB::table($table)->where('id', $id)->first();
        if (isset($unitIds)) {
            $after['created_unit_ids'] = $unitIds;
        }
        if (isset($photoIds)) {
            $after['photo_ids'] = $photoIds;
        }

        return [$type, (int) $id, $before, $after, $reason];
    }

    private function addUnits(object $model, int $quantity, string $condition, string $now): array
    {
        $numbers = InventoryRules::reserve((int) $model->next_unit_no, $quantity);
        $maximum = DB::table('equipment_units')->where('model_id', $model->id)->max('unit_no') ?? 0;
        if ($model->next_unit_no <= $maximum) {
            throw ValidationException::withMessages(['quantity' => 'Counter tidak konsisten dengan unit existing; hentikan dan periksa tanpa mengubah schema.']);
        }
        $ids = [];
        foreach ($numbers as $number) {
            $ids[] = DB::table('equipment_units')->insertGetId([
                'model_id' => $model->id, 'unit_no' => $number, 'asset_code' => InventoryRules::assetCode($model->model_code, $number),
                'physical_condition' => $condition, 'operational_status' => 'active', 'version' => 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        DB::table('equipment_models')->where('id', $model->id)->update(['next_unit_no' => $model->next_unit_no + $quantity, 'updated_at' => $now]);

        return $ids;
    }

    private function lockUnitModel(int $unitId): void
    {
        $unit = DB::table('equipment_units')->where('id', $unitId)->first();
        abort_unless($unit, 404);
        DB::table('equipment_models')->where('id', $unit->model_id)->lockForUpdate()->first();
    }

    private function borrowed(int $unitId): bool
    {
        return DB::table('equipment_loan_units')->where('unit_id', $unitId)->where('outcome', 'on_loan')->exists();
    }

    private function modelBorrowed(int $modelId): bool
    {
        return DB::table('equipment_loan_units as l')->join('equipment_units as u', 'u.id', '=', 'l.unit_id')->where('u.model_id', $modelId)->where('l.outcome', 'on_loan')->exists();
    }

    public function units()
    {
        return DB::table('equipment_units as u')->join('equipment_models as m', 'm.id', '=', 'u.model_id')
            ->join('equipment_categories as c', 'c.id', '=', 'm.category_id')
            ->select('u.*', 'm.name as model_name', 'm.model_code', 'm.is_active as model_active', 'c.name as category_name')
            ->selectRaw("EXISTS(SELECT 1 FROM equipment_loan_units lu WHERE lu.unit_id=u.id AND lu.outcome='on_loan') as borrowed");
    }

    public function stock(): array
    {
        $rows = $this->units()->get();
        $counts = array_fill_keys(['available', 'on_loan', 'maintenance', 'damaged', 'lost', 'retired', 'model_inactive', 'integrity_error'], 0);
        foreach ($rows as $unit) {
            $group = $unit->borrowed && $unit->operational_status !== 'active' ? 'integrity_error'
                : match (true) {
                    $unit->operational_status === 'retired' => 'retired',
                    $unit->operational_status === 'lost' => 'lost',
                    (bool) $unit->borrowed => 'on_loan',
                    $unit->operational_status === 'maintenance' => 'maintenance',
                    $unit->physical_condition !== 'good' => 'damaged',
                    ! $unit->model_active => 'model_inactive',
                    default => 'available',
                };
            $counts[$group]++;
        }

        return $counts;
    }
}
