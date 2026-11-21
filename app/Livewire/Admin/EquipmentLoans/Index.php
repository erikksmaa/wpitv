<?php

namespace App\Livewire\Admin\EquipmentLoans;

use App\Livewire\Concerns\HandlesEquipmentErrors;
use App\Models\EquipmentLoan;
use App\Support\InputBounds;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
class Index extends Component
{
    use AuthorizesRequests, WithPagination;
    use HandlesEquipmentErrors;

    protected string $paginationTheme = 'bootstrap';

    // Borrower searches stay out of URL/history.
    public string $search = '';

    public string $status = 'all';

    public int $perPage = 10;

    public function mount(): void
    {
        $this->authorize('viewAny', EquipmentLoan::class);
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

    public function render()
    {
        $this->authorize('viewAny', EquipmentLoan::class);
        $this->search = InputBounds::search($this->search);
        $this->perPage = InputBounds::perPage($this->perPage);
        $q = EquipmentLoan::withCount(['items', 'items as open_items_count' => fn ($q) => $q->whereNull('returned_at')]);
        $q->when($this->search !== '', fn ($q) => $q->where(fn ($q) => $q->where('loan_code', 'like', '%'.$this->search.'%')->orWhere('borrower_name', 'like', '%'.$this->search.'%')->orWhere('student_number', 'like', '%'.$this->search.'%')));
        if ($this->status === 'active') {
            $q->whereHas('items')->whereDoesntHave('items', fn ($q) => $q->whereNotNull('returned_at'));
        }
        if ($this->status === 'partially_returned') {
            $q->whereHas('items', fn ($q) => $q->whereNull('returned_at'))->whereHas('items', fn ($q) => $q->whereNotNull('returned_at'));
        }
        if ($this->status === 'returned') {
            $q->whereHas('items')->whereDoesntHave('items', fn ($q) => $q->whereNull('returned_at'));
        }
        if ($this->status === 'overdue') {
            $q->where('due_at', '<', now('UTC')->format('Y-m-d H:i:s.u'))->whereHas('items', fn ($q) => $q->whereNull('returned_at'));
        }
        $loans = $q->orderByDesc('id')->paginate($this->perPage);

        return view('livewire.admin.equipment-loans.index', compact('loans'))->title('Peminjaman Alat');
    }
}
