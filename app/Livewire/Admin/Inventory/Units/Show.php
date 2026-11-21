<?php

namespace App\Livewire\Admin\Inventory\Units;

use App\Livewire\Concerns\HandlesEquipmentErrors;
use App\Models\EquipmentAuditEvent;
use App\Models\EquipmentLoanItem;
use App\Models\InventoryUnit;
use App\Services\EquipmentPhotos;
use App\Services\EquipmentQuantity;
use App\Services\EquipmentStock;
use App\Services\InventoryUnitRecovery;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
class Show extends Component
{
    use AuthorizesRequests, WithPagination;
    use HandlesEquipmentErrors;

    protected string $paginationTheme = 'bootstrap';

    #[Locked]
    public int $unitId;

    #[Locked]
    public int $expectedVersion;

    public int $quantity = 1;

    public string $adjustReason = '';

    public string $adjustment = 'add';

    public function adjustStock(EquipmentQuantity $groups)
    {
        $this->authorize('update', InventoryUnit::findOrFail($this->unitId));
        $this->validate(['adjustment' => 'required|in:add,archive', 'adjustReason' => 'required|string|max:1000', 'quantity' => 'required|integer|min:1|max:50']);
        $groups->adjust(auth()->user(), $this->unitId, $this->expectedVersion, $this->quantity, $this->adjustReason, $this->adjustment === 'add');
        session()->flash('success', 'Stok barang berhasil disesuaikan.');

        return redirect()->route('admin.inventory.units.show', $this->unitId);
    }

    public string $inspectionNotes = '';

    public bool $inspectionConfirmed = false;

    public function mount(InventoryUnit $unit): void
    {
        $this->authorize('view', $unit);
        $this->unitId = $unit->id;
        $this->expectedVersion = $unit->version;
    }

    // A dedicated recovery action, not a generic status toggle. The action name
    // also uses the existing SweetAlert2 status-change confirmation.
    public function updateStatus(InventoryUnitRecovery $recovery)
    {
        $unit = InventoryUnit::findOrFail($this->unitId);
        $this->authorize('reactivateRecovered', $unit);
        $recovery->reactivate(auth()->user(), $this->unitId, $this->expectedVersion, $this->inspectionNotes, $this->inspectionConfirmed);
        session()->flash('success', 'Unit diaktifkan kembali setelah inspeksi. Histori kehilangan tetap tersimpan.');

        return redirect()->route('admin.inventory.units.show', $unit);
    }

    public function render()
    {
        $unit = app(EquipmentStock::class)->units()->with('category')->where('inventory_units.id', $this->unitId)->firstOrFail();
        $this->authorize('view', $unit);
        $borrowed = $unit->loanItems()->whereNull('returned_at')->exists();
        $hasLossHistory = $unit->loanItems()->whereNotNull('lost_at')->exists();

        $photos = EquipmentPhotos::enabled() ? DB::table('inventory_unit_photos')->where('unit_id', $unit->id)->orderByDesc('revision')->paginate(10, ['id', 'revision'], 'photoPage') : collect();

        $groups = app(EquipmentQuantity::class);
        $parent = $groups->parentId($unit->id);
        $isParent = $parent === $unit->id;
        $item = $isParent && $unit->item_id !== null ? $unit->item()->with('category')->first() : null;
        $coverHistory = $item ? DB::table('inventory_item_photos')->where('item_id', $item->id)->orderByDesc('revision')
            ->paginate(10, ['id', 'revision'], 'coverPage') : collect();
        $unitNumber = $isParent ? 1 : $groups->members($parent)->where('inventory_units.id', '<=', $unit->id)->count();
        $counts = app(EquipmentStock::class)->counts($groups->members($parent));
        $members = $groups->members($parent)->orderBy('inventory_units.id')->paginate(12, ['*'], 'memberPage');
        $groupUnits = $groups->members($parent)->select('inventory_units.id');
        $history = EquipmentLoanItem::query()
            ->when($parent === $unit->id, fn ($q) => $q->whereIn('inventory_unit_id', clone $groupUnits), fn ($q) => $q->where('inventory_unit_id', $unit->id))
            ->with('loan:id,loan_code,borrowed_at,due_at')->latest('id')->paginate(10);
        $events = EquipmentAuditEvent::whereIn('unit_id', clone $groupUnits)->orWhereIn('loan_id', DB::table('equipment_loan_items')->select('loan_id')->whereIn('inventory_unit_id', $groupUnits))->latest('id')->paginate(10, ['id', 'event_type', 'actor_name', 'created_at', 'loan_id'], 'eventPage');
        $changes = EquipmentAuditEvent::where('unit_id', $parent)->whereIn('after_data->operation', ['stock_added', 'stock_archived'])->latest('id')->paginate(10, ['actor_name', 'created_at', 'reason', 'before_data', 'after_data'], 'stockPage');
        $cover = $groups->photos([$parent])->get($parent);

        return view('livewire.admin.inventory.units.show', compact('item', 'coverHistory', 'isParent', 'unitNumber', 'members', 'events', 'parent', 'counts', 'changes', 'cover', 'unit', 'borrowed', 'hasLossHistory', 'photos', 'history'))->title('Detail Unit Inventaris');
    }
}
