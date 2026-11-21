<?php

namespace App\Livewire\Admin\Inventory\Units;

use App\Livewire\Concerns\HandlesEquipmentErrors;
use App\Models\InventoryCategory;
use App\Models\InventoryUnit;
use App\Services\EquipmentQuantity;
use App\Services\EquipmentStock;
use App\Support\InputBounds;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
class Index extends Component
{
    use AuthorizesRequests, WithPagination;
    use HandlesEquipmentErrors;

    protected string $paginationTheme = 'bootstrap';

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = 'all';

    #[Url]
    public string $sortField = 'name';

    #[Url]
    public string $sortDirection = 'asc';

    public int $perPage = 10;

    #[Url]
    public string $category = '';

    #[Url]
    public string $availability = '';

    public function updatingCategory(): void
    {
        $this->resetPage();
    }

    public function updatingAvailability(): void
    {
        $this->resetPage();
    }

    public function mount(): void
    {
        $this->authorize('viewAny', InventoryUnit::class);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        $this->authorize('viewAny', InventoryUnit::class);
        if (! in_array($field, ['inventory_code', 'name', 'condition', 'is_active', 'created_at'], true)) {
            return;
        }
        $this->sortDirection = $this->sortField === $field && $this->sortDirection === 'asc' ? 'desc' : 'asc';
        $this->sortField = $field;
        $this->resetPage();
    }

    public function render()
    {
        $this->authorize('viewAny', InventoryUnit::class);
        $this->search = InputBounds::search($this->search);
        $this->perPage = InputBounds::perPage($this->perPage);
        $field = in_array($this->sortField, ['inventory_code', 'name', 'condition', 'is_active', 'created_at'], true) ? $this->sortField : 'name';
        $direction = $this->sortDirection === 'desc' ? 'desc' : 'asc';
        $stock = app(EquipmentStock::class);
        $units = $stock->products()->with('category:id,name')->when($this->search !== '', fn ($q) => $q->where(function ($q) {
            foreach (['name', 'model', 'brand', 'inventory_code'] as $column) {
                $q->orWhere($column, 'like', '%'.$this->search.'%');
            }
        }))->when(ctype_digit($this->category), fn ($q) => $q->where('category_id', $this->category))->when(in_array($this->status, ['0', '1'], true), fn ($q) => $q->where('is_active', $this->status === '1'))->when($this->availability === 'available', fn ($q) => $q->where('available', '>', 0))->when($this->availability === 'unavailable', fn ($q) => $q->where('available', 0));
        $counts = $stock->counts($stock->units());
        $units = $units
            ->orderBy('inventory_units.'.$field, $direction)->orderBy('inventory_units.id')->paginate($this->perPage);

        $photoLinks = app(EquipmentQuantity::class)->photos($units->pluck('id'));
        $categories = InventoryCategory::orderBy('name')->get(['id', 'name']);

        return view('livewire.admin.inventory.units.index', compact('photoLinks', 'units', 'categories', 'counts'))->title('Unit Inventaris');
    }
}
