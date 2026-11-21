<?php

namespace App\Livewire\Admin\EquipmentLoans;

use App\Livewire\Concerns\HandlesEquipmentErrors;
use App\Models\EquipmentLoan;
use App\Services\EquipmentReports;
use App\Support\InputBounds;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
class Reports extends Component
{
    use AuthorizesRequests, WithPagination;
    use HandlesEquipmentErrors;

    protected string $paginationTheme = 'bootstrap';

    public string $period = 'this_month';

    public string $startDate = '';

    public string $endDate = '';

    public string $dateBasis = 'borrowed_at';

    public string $categoryId = '';

    public string $condition = 'all';

    public string $status = 'all';

    public int $perPage = 10;

    #[Locked]
    public array $applied = [];

    public function mount(EquipmentReports $reports): void
    {
        $this->authorize('viewReport', EquipmentLoan::class);
        $this->applyFilters($reports);
    }

    public function applyFilters(EquipmentReports $reports): void
    {
        $this->authorize('viewReport', EquipmentLoan::class);
        $this->resetValidation();
        $filters = $reports->normalizeFilters([
            'period' => $this->period, 'startDate' => $this->startDate, 'endDate' => $this->endDate,
            'dateBasis' => $this->dateBasis, 'categoryId' => $this->categoryId, 'condition' => $this->condition, 'status' => $this->status,
        ]);
        // Freeze effective calendar dates so exports use the displayed period,
        // even if a preset crosses midnight before the download is requested.
        $this->applied = $filters;
        $this->startDate = $filters['startDate'];
        $this->endDate = $filters['endDate'];
        $this->resetPage('loansPage');
        $this->resetPage('inventoryPage');
    }

    public function updatingPerPage(): void
    {
        $this->resetPage('loansPage');
        $this->resetPage('inventoryPage');
    }

    public function render(EquipmentReports $reports)
    {
        $this->authorize('viewReport', EquipmentLoan::class);
        $this->perPage = InputBounds::perPage($this->perPage);
        $data = $reports->page(auth()->user(), $this->applied, $this->perPage);

        return view('livewire.admin.equipment-loans.reports', $data)->title('Laporan Inventaris & Peminjaman');
    }
}
