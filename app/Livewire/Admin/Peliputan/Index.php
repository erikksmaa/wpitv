<?php

namespace App\Livewire\Admin\Peliputan;

use App\Models\Coverage;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('Peliputan & Liputan Acara - WPiTV Admin')]
class Index extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    protected string $paginationTheme = 'bootstrap';

    #[Url(history: true)]
    public string $search = '';

    #[Url(history: true)]
    public string $statusFilter = '';

    #[Url(history: true)]
    public string $dateFrom = '';

    #[Url(history: true)]
    public string $dateTo = '';

    #[Url(history: true)]
    public string $sortField = 'created_at';

    #[Url(history: true)]
    public string $sortDirection = 'desc';

    public int $perPage = 10;

    // Quick Status Update State
    public ?int $selectedCoverageId = null;

    public string $newStatus = '';

    public string $adminNote = '';

    public bool $showStatusModal = false;

    // View Details Modal State
    public ?Coverage $selectedCoverage = null;

    public bool $showDetailModal = false;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatingDateTo(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        $allowedFields = ['event_date', 'created_at', 'event_name', 'name', 'status'];
        if (! in_array($field, $allowedFields, true)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = in_array($field, ['created_at', 'event_date'], true) ? 'desc' : 'asc';
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'statusFilter', 'dateFrom', 'dateTo']);
        $this->resetPage();
    }

    public function openDetailModal(int $id): void
    {
        $coverage = Coverage::findOrFail($id);
        $this->authorize('view', $coverage);

        $this->selectedCoverage = $coverage;
        $this->showDetailModal = true;
    }

    public function closeDetailModal(): void
    {
        $this->showDetailModal = false;
        $this->selectedCoverage = null;
    }

    public function openStatusModal(int $id): void
    {
        $coverage = Coverage::findOrFail($id);
        $this->authorize('updateStatus', $coverage);

        $this->selectedCoverageId = $coverage->id;
        $this->newStatus = $coverage->status;
        $this->adminNote = $coverage->admin_note ?? '';
        $this->showStatusModal = true;
    }

    public function closeStatusModal(): void
    {
        $this->showStatusModal = false;
        $this->reset(['selectedCoverageId', 'newStatus', 'adminNote']);
    }

    public function updateStatus(): void
    {
        $coverage = Coverage::findOrFail($this->selectedCoverageId);
        $this->authorize('updateStatus', $coverage);

        $allowedStatuses = [
            Coverage::STATUS_PENDING,
            Coverage::STATUS_APPROVED,
            Coverage::STATUS_IN_PROGRESS,
            Coverage::STATUS_COMPLETED,
            Coverage::STATUS_REJECTED,
        ];

        if (! in_array($this->newStatus, $allowedStatuses, true)) {
            session()->flash('error', 'Status yang dipilih tidak valid.');

            return;
        }

        $coverage->update([
            'status' => $this->newStatus,
            'admin_note' => $this->adminNote,
        ]);

        $this->closeStatusModal();
        session()->flash('success', "Status permohonan \"{$coverage->submission_code}\" berhasil diperbarui.");
    }

    public function delete(int $id): void
    {
        $coverage = Coverage::findOrFail($id);
        $this->authorize('delete', $coverage);

        $code = $coverage->submission_code;
        $coverage->delete();

        session()->flash('success', "Data permohonan peliputan \"{$code}\" berhasil dihapus.");
    }

    public function render()
    {
        $this->authorize('viewAny', Coverage::class);

        $allowedSortFields = ['event_date', 'created_at', 'event_name', 'name', 'status'];
        $sortField = in_array($this->sortField, $allowedSortFields, true) ? $this->sortField : 'created_at';
        $sortDirection = in_array($this->sortDirection, ['asc', 'desc'], true) ? $this->sortDirection : 'desc';

        // Summary Statistics Aggregation
        $stats = [
            'total' => Coverage::count(),
            'pending' => Coverage::where('status', Coverage::STATUS_PENDING)->count(),
            'approved' => Coverage::where('status', Coverage::STATUS_APPROVED)->count(),
            'in_progress' => Coverage::where('status', Coverage::STATUS_IN_PROGRESS)->count(),
            'completed' => Coverage::where('status', Coverage::STATUS_COMPLETED)->count(),
            'rejected' => Coverage::where('status', Coverage::STATUS_REJECTED)->count(),
        ];

        // Main Query
        $coverages = Coverage::query()
            ->when($this->search !== '', function ($query) {
                $query->where(function ($q) {
                    $q->where('submission_code', 'like', '%'.$this->search.'%')
                        ->orWhere('name', 'like', '%'.$this->search.'%')
                        ->orWhere('email', 'like', '%'.$this->search.'%')
                        ->orWhere('phone', 'like', '%'.$this->search.'%')
                        ->orWhere('event_name', 'like', '%'.$this->search.'%')
                        ->orWhere('location', 'like', '%'.$this->search.'%')
                        ->orWhere('description', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->statusFilter !== '', function ($query) {
                $query->where('status', $this->statusFilter);
            })
            ->when($this->dateFrom !== '', function ($query) {
                $query->whereDate('event_date', '>=', $this->dateFrom);
            })
            ->when($this->dateTo !== '', function ($query) {
                $query->whereDate('event_date', '<=', $this->dateTo);
            })
            ->orderBy($sortField, $sortDirection)
            ->paginate($this->perPage);

        return view('livewire.admin.peliputan.index', [
            'coverages' => $coverages,
            'stats' => $stats,
            'statusOptions' => Coverage::getStatusOptions(),
        ]);
    }
}
