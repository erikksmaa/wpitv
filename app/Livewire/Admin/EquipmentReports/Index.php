<?php

namespace App\Livewire\Admin\EquipmentReports;

use App\Services\Equipment\EquipmentReports;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('Laporan Peralatan - WPiTV')]
class Index extends Component
{
    use WithPagination;

    protected string $paginationTheme = 'bootstrap';

    #[Locked]
    public string $section = 'inventory';

    #[Locked]
    public array $filters = [];

    public array $draft = [];

    public function mount(string $section = 'inventory'): void
    {
        Gate::authorize('equipment.report.view');
        $this->section = $section;
    }

    public function apply(EquipmentReports $reports): void
    {
        Gate::authorize('equipment.report.view');
        $this->resetValidation();
        try {
            $this->filters = $reports->filters($this->section, $this->draft);
        } catch (QueryException $error) {
            report($error);
            $this->addError('action', 'Filter belum dapat diterapkan. Silakan coba lagi.');
            return;
        }
        $this->resetPage();
    }

    public function clear(): void
    {
        Gate::authorize('equipment.report.view');
        $this->reset('draft', 'filters');
        $this->resetValidation();
        $this->resetPage();
    }

    public function render(EquipmentReports $reports)
    {
        Gate::authorize('equipment.report.view');
        try {
            $data = match ($this->section) {
                'inventory' => [
                    'counts' => $reports->stock($this->filters),
                    'rows' => $reports->inventory($this->filters)->paginate(20),
                    'categories' => DB::table('equipment_categories')->orderBy('name')->get(['id','name']),
                    'models' => DB::table('equipment_models')->orderBy('name')->get(['id','name','model_code','category_id'])->keyBy('id'),
                ],
                'loans' => $this->loanData($reports),
            };
        } catch (QueryException $error) {
            report($error);
            $data = ['failed' => true];
        }
        return view('livewire.admin.equipment-reports.index', $data);
    }

    private function loanData(EquipmentReports $reports): array
    {
        $query = $reports->loans($this->filters);
        return ['counts' => $reports->loanCounts($query, ($this->filters['period'] ?? '') === 'return'), 'rows' => $query->paginate(20)];
    }
}
