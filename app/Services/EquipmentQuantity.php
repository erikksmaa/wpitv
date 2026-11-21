<?php

namespace App\Services;

use App\Exceptions\EquipmentOperationException;
use App\Models\EquipmentAuditEvent;
use App\Models\InventoryCategory;
use App\Models\InventoryUnit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class EquipmentQuantity
{
    public function parentId(int $unitId): int
    {
        if (EquipmentStructure::installed() && ($item = DB::table('inventory_units')->where('id', $unitId)->value('item_id'))) {
            return (int) $item;
        }

        return (int) (DB::table('inventory_unit_members')->where('unit_id', $unitId)->value('parent_id') ?? $unitId);
    }

    public function photos($ids)
    {
        $parents = DB::table('inventory_unit_members')->whereIn('unit_id', $ids)->pluck('parent_id', 'unit_id');
        $owners = collect($ids)->merge($parents->values())->unique();
        $latest = DB::table('inventory_unit_photos')->whereIn('unit_id', $owners)->groupBy('unit_id')->selectRaw('unit_id, MAX(id) AS photo_id')->pluck('photo_id', 'unit_id');

        $covers = EquipmentStructure::installed()
            ? DB::table('inventory_item_photos')->whereIn('item_id', $owners)->orderByDesc('revision')->get(['id', 'item_id'])->unique('item_id')->keyBy('item_id')
            : collect();

        return collect($ids)->mapWithKeys(function ($id) use ($parents, $latest, $covers) {
            $owner = $latest->has($id) ? $id : $parents->get($id, $id);
            if (! $parents->has($owner) && ($cover = $covers->get($owner))) {
                return [$id => [$owner, $cover->id, 'cover' => 1]];
            }

            return [$id => $latest->has($owner) ? [$owner, $latest[$owner]] : null];
        });
    }

    public function members(int $parent)
    {
        $stock = app(EquipmentStock::class);

        return $stock->units()->whereRaw($stock->groupExpression().' = ?', [$parent]);
    }

    public function available(int $parent)
    {
        $status = app(EquipmentStock::class)->statusExpression();

        return $this->members($parent)->whereRaw("$status = ?", ['available'])->orderBy('inventory_units.id');
    }

    public function adjust(User $actor, int $parent, int $expected, int $amount, string $reason, bool $add, bool $initial = false): void
    {
        Validator::make(compact('amount', 'reason'), ['amount' => 'required|integer|min:1|max:50', 'reason' => 'required|string|max:1000'])->validate();
        DB::transaction(function () use ($actor, $parent, $expected, $amount, $reason, $add, $initial) {
            $root = InventoryUnit::lockForUpdate()->findOrFail($parent);
            Gate::forUser($actor->fresh('role'))->authorize('update', $root);
            if ($this->parentId($parent) !== $parent || $root->version !== $expected) {
                throw EquipmentOperationException::stale('quantity');
            }
            $expanded = EquipmentStructure::installed();
            $itemId = $expanded
                ? ($root->item_id ?? EquipmentStructure::syncItem(DB::connection(), (object) $root->getAttributes()))
                : null;
            $master = $itemId !== null ? DB::table('inventory_items')->where('id', $itemId)->lockForUpdate()->sole() : null;
            $stock = app(EquipmentStock::class);
            $before = $stock->counts($this->members($parent));
            $ids = [];
            if ($add) {
                if (! InventoryCategory::whereKey($master?->category_id ?? $root->category_id)->lockForUpdate()->firstOrFail()->is_active || ! DB::table('inventory_unit_photos')->where('unit_id', $parent)->exists()) {
                    throw ValidationException::withMessages(['quantity' => 'Kategori aktif dan foto cover diperlukan untuk menambah stok.']);
                }
                // The parent lock serializes numbering, including units that have been archived.
                $number = $master ? $master->next_unit_number - 1 : 1 + DB::table('inventory_unit_members')->where('parent_id', $parent)->count();
                for ($i = 0; $i < $amount; $i++) {
                    $unit = $root->replicate();
                    do {
                        $suffix = $master ? '-U'.str_pad((string) ++$number, 2, '0', STR_PAD_LEFT) : '-'.++$number;
                        $unit->inventory_code = mb_substr($root->inventory_code, 0, 50 - strlen($suffix)).$suffix;
                    } while (InventoryUnit::where('inventory_code', $unit->inventory_code)->exists());
                    if ($itemId !== null) {
                        $unit->item_id = $itemId;
                        foreach (['name', 'brand', 'model', 'category_id'] as $field) {
                            $unit->{$field} = $master->{$field};
                        }
                    }
                    $unit->serial_number = null;
                    $unit->version = 1;
                    $unit->condition = $initial ? $root->condition : 'good';
                    $unit->is_active = $initial ? $root->is_active : true;
                    $unit->save();
                    DB::table('inventory_unit_members')->insert(['unit_id' => $unit->id, 'parent_id' => $parent]);
                    $ids[] = $unit->id;
                    $this->audit($actor, $unit, 'created', $reason, null, ['parent_id' => $parent, 'inventory_code' => $unit->inventory_code]);
                }
                if ($itemId !== null) {
                    DB::table('inventory_items')->where('id', $itemId)->update(['next_unit_number' => $number + 1]);
                }
            } else {
                $candidates = $this->available($parent)->reorder()->orderByRaw('inventory_units.id = ?', [$parent])->orderBy('inventory_units.id')->limit($amount)->pluck('inventory_units.id');
                if ($candidates->count() !== $amount) {
                    throw ValidationException::withMessages(['quantity' => 'Stok tersedia tidak cukup.']);
                }
                foreach ($candidates as $id) {
                    $unit = InventoryUnit::lockForUpdate()->findOrFail($id);
                    if (! $unit->is_active || $unit->condition !== 'good' || $unit->loanItems()->whereNull('returned_at')->lockForUpdate()->first(['id'])) {
                        throw EquipmentOperationException::stale('quantity');
                    }
                    $unit->is_active = false;
                    if ($expanded) {
                        $unit->retired_at = now('UTC')->format('Y-m-d H:i:s.u');
                        $unit->retired_reason = $reason;
                    }
                    $unit->version++;
                    $unit->save();
                    $ids[] = $id;
                    $this->audit($actor, $unit, 'archived', $reason, ['is_active' => true], ['is_active' => false]);
                }
            }
            $root->refresh();
            $root->version++;
            $root->save();
            $this->audit($actor, $root, 'updated', $reason, $before, ['operation' => $add ? 'stock_added' : 'stock_archived', 'units' => $ids, 'stock' => $stock->counts($this->members($parent))]);
        }, 3);
    }

    private function audit(User $actor, InventoryUnit $unit, string $type, string $reason, ?array $before, array $after): void
    {
        EquipmentAuditEvent::forceCreate(['unit_id' => $unit->id, 'actor_id' => $actor->id, 'actor_name' => $actor->name, 'event_type' => $type, 'reason' => $reason, 'before_data' => $before, 'after_data' => $after]);
    }
}
