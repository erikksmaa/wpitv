<?php

namespace App\Livewire\Admin\Inventory\Units;

use App\Livewire\Concerns\HandlesEquipmentErrors;
use App\Models\InventoryUnit;
use App\Services\EquipmentQuantity;
use App\Services\EquipmentStock;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Component;

class StockAdjustment extends Component
{
    use AuthorizesRequests, HandlesEquipmentErrors;

    #[Locked]
    public int $parentId;

    #[Locked]
    public int $expectedVersion;

    public string $action = 'add';

    public int $quantity = 1;

    public string $reason = '';

    public function mount(int $unitId, EquipmentQuantity $groups): void
    {
        $this->authorize('update', InventoryUnit::findOrFail($unitId));
        $this->parentId = $groups->parentId($unitId);
        $this->expectedVersion = $this->parent()->version;
    }

    private function parent(): InventoryUnit
    {
        $parent = InventoryUnit::findOrFail($this->parentId);
        $this->authorize('update', $parent);

        return $parent;
    }

    public function save(EquipmentQuantity $groups): void
    {
        $this->parent();
        $this->validate([
            'action' => 'required|in:add,archive',
            'quantity' => 'required|integer|min:1|max:50',
            'reason' => 'required|string|max:1000',
        ]);
        $groups->adjust(auth()->user(), $this->parentId, $this->expectedVersion, $this->quantity, $this->reason, $this->action === 'add');
        $this->expectedVersion = $this->parent()->version;
        $this->reset('quantity', 'reason');
        session()->flash('stock_success', 'Penyesuaian stok tersimpan.');
    }

    public function render(EquipmentQuantity $groups, EquipmentStock $stock)
    {
        $parent = $this->parent();

        return view('livewire.admin.inventory.units.stock-adjustment', [
            'parent' => $parent,
            'counts' => $stock->counts($groups->members($parent->id)),
            'hasCover' => DB::table('inventory_unit_photos')->where('unit_id', $parent->id)->exists(),
        ]);
    }
}
