<?php

namespace App\Livewire\Admin\Inventory\Categories;

use App\Livewire\Concerns\HandlesEquipmentErrors;
use App\Models\InventoryCategory;
use App\Services\EquipmentStructure;
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

    public function mount(): void
    {
        $this->authorize('viewAny', InventoryCategory::class);
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
        $this->authorize('viewAny', InventoryCategory::class);
        if (! in_array($field, ['name', 'is_active', 'created_at'], true)) {
            return;
        }
        $this->sortDirection = $this->sortField === $field && $this->sortDirection === 'asc' ? 'desc' : 'asc';
        $this->sortField = $field;
        $this->resetPage();
    }

    public function render()
    {
        $this->authorize('viewAny', InventoryCategory::class);
        $this->search = InputBounds::search($this->search);
        $this->perPage = InputBounds::perPage($this->perPage);
        $field = in_array($this->sortField, ['name', 'is_active', 'created_at'], true) ? $this->sortField : 'name';
        $direction = $this->sortDirection === 'desc' ? 'desc' : 'asc';
        $expanded = EquipmentStructure::installed();
        $categories = InventoryCategory::query()
            ->when($expanded, fn ($q) => $q->withCount(['items', 'itemUnits']), fn ($q) => $q->withCount('units'))
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
            ->when(in_array($this->status, ['0', '1'], true), fn ($q) => $q->where('is_active', $this->status === '1'))
            ->orderBy($field, $direction)->orderBy('id')->paginate($this->perPage);

        return view('livewire.admin.inventory.categories.index', compact('categories'))->title('Kategori Inventaris');
    }
}
