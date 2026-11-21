<?php

namespace App\Services;

use App\Exceptions\EquipmentOperationException;
use App\Models\EquipmentAuditEvent;
use App\Models\InventoryUnit;
use App\Models\User;
use App\Support\EquipmentErrors;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class InventoryUnitRecovery
{
    public function reactivate(User $actor, int $unitId, int $expectedVersion, string $inspectionNotes, bool $inspectionConfirmed): InventoryUnit
    {
        return EquipmentErrors::run(fn () => $this->performReactivate($actor, $unitId, $expectedVersion, $inspectionNotes, $inspectionConfirmed));
    }

    private function performReactivate(User $actor, int $unitId, int $expectedVersion, string $inspectionNotes, bool $inspectionConfirmed): InventoryUnit
    {
        $inspectionNotes = trim(str_replace(["\r\n", "\r"], "\n", $inspectionNotes));
        Validator::make([
            'expectedVersion' => $expectedVersion, 'inspectionNotes' => $inspectionNotes, 'inspectionConfirmed' => $inspectionConfirmed,
        ], [
            'expectedVersion' => ['required', 'integer', 'min:1'],
            'inspectionNotes' => ['required', 'string', 'max:1000'],
            'inspectionConfirmed' => ['accepted'],
        ], [], ['inspectionNotes' => 'hasil dan alasan inspeksi', 'inspectionConfirmed' => 'konfirmasi inspeksi'])->validate();

        return DB::transaction(function () use ($actor, $unitId, $expectedVersion, $inspectionNotes): InventoryUnit {
            // Master action: lock the unit, then read its details. Never acquire
            // a loan-header lock from this path (which would invert loss/return locks).
            $unit = InventoryUnit::lockForUpdate()->findOrFail($unitId);
            $actor = User::with('role')->useWritePdo()->findOrFail($actor->id);
            Gate::forUser($actor)->authorize('reactivateRecovered', $unit);
            if ($unit->version !== $expectedVersion || $unit->is_active) {
                throw EquipmentOperationException::stale('inspectionNotes');
            }
            if ($unit->loanItems()->whereNull('returned_at')->lockForUpdate()->first(['id'])) {
                throw ValidationException::withMessages(['inspectionNotes' => 'Unit belum kembali dan tidak dapat diaktifkan.']);
            }
            if (! $unit->loanItems()->whereNotNull('lost_at')->lockForUpdate()->first(['id'])) {
                throw ValidationException::withMessages(['inspectionNotes' => 'Aksi ini khusus unit dengan histori kehilangan.']);
            }
            if ($unit->condition !== 'good') {
                throw ValidationException::withMessages(['inspectionNotes' => 'Unit harus berkondisi baik. Catat kondisi hasil inspeksi di form unit terlebih dahulu.']);
            }

            $before = $unit->only(['is_active', 'condition', 'version']);
            $now = CarbonImmutable::now('UTC');
            $unit->is_active = true;
            $unit->version++;
            $unit->updated_at = $now;
            $unit->save();
            $audit = new EquipmentAuditEvent;
            $audit->unit_id = $unit->id;
            $audit->event_type = 'updated';
            $audit->actor_id = $actor->id;
            $audit->actor_name = $actor->name;
            $audit->reason = $inspectionNotes;
            $audit->before_data = $before;
            $audit->after_data = [...$unit->only(['is_active', 'condition', 'version']), 'action' => 'reactivate_recovered', 'inspection_confirmed' => true];
            $audit->created_at = $now;
            $audit->save();

            return $unit;
        }, 3);
    }
}
