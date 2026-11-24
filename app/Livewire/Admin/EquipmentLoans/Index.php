<?php

namespace App\Livewire\Admin\EquipmentLoans;

use App\Services\Equipment\LoanService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('Peminjaman Peralatan - WPiTV')]
class Index extends Component
{
    use WithPagination;

    protected string $paginationTheme = 'bootstrap';

    #[Locked]
    public bool $creating = false;

    #[Locked]
    public string $token = '';

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $borrowerType = '';

    public int $perPage = 10;

    public array $selected = [];

    public array $form = ['borrower_type' => 'student', 'borrower_name' => '', 'identity_number' => '', 'borrower_contact' => '', 'borrower_affiliation' => '', 'purpose' => '', 'due_date' => ''];

    public function mount(): void
    {
        Gate::authorize('equipment.loan.view');
        $this->creating = request()->routeIs('admin.equipment-loans.create');
        if ($this->creating) {
            Gate::authorize('equipment.loan.checkout');
        }
        $this->token = (string) Str::uuid();
        $this->form['due_date'] = now('Asia/Jakarta')->toDateString();
    }

    public function updated($property): void
    {
        if (in_array($property, ['search', 'status', 'borrowerType', 'perPage'], true)) {
            $this->resetPage('availablePage');
            $this->resetPage('loansPage');
        }
    }

    public function removeUnit(int $id): void
    {
        $this->selected = array_values(array_filter($this->selected, fn ($value) => (int) $value !== $id));
    }

    public function checkout(LoanService $service): void
    {
        Gate::authorize('equipment.loan.checkout');
        abort_unless($this->creating, 403);
        $id = $service->execute(auth()->user(), 'loan.checkout', $this->token, $this->form + ['units' => $this->selected]);
        session()->flash('success', 'Peminjaman tersimpan. Periksa detail sebelum menyerahkan unit.');
        $this->redirectRoute('admin.equipment-loans.show', ['loanId' => $id]);
    }

    public function render(LoanService $service)
    {
        Gate::authorize('equipment.loan.view');
        $search = '%'.mb_substr(trim($this->search), 0, 100).'%';
        if ($this->creating) {
            Gate::authorize('equipment.loan.checkout');
            $available = DB::table('equipment_units as u')->join('equipment_models as m', 'm.id', '=', 'u.model_id')
                ->join('equipment_categories as c', 'c.id', '=', 'm.category_id')
                ->where('m.is_active', 1)->where('u.operational_status', 'active')->where('u.physical_condition', 'good')
                ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('equipment_loan_units as lu')->whereColumn('lu.unit_id', 'u.id')->where('lu.outcome', 'on_loan'))
                ->where(fn ($q) => $q->where('u.asset_code', 'like', $search)->orWhere('m.name', 'like', $search)->orWhere('c.name', 'like', $search))
                ->select('u.id', 'u.asset_code', 'm.name as model_name', 'c.name as category_name')->orderBy('m.name')->orderBy('u.id');
            $selection = array_slice(array_map('intval', $this->selected), 0, 50);

            return view('livewire.admin.equipment-loans.index', [
                'available' => $available->paginate(10, ['*'], 'availablePage'),
                'basket' => DB::table('equipment_units as u')->join('equipment_models as m', 'm.id', '=', 'u.model_id')->whereIn('u.id', $selection)
                    ->select('u.id', 'u.asset_code', 'm.name as model_name')->orderBy('u.id')->get(),
            ]);
        }
        $query = $service->listing()->where(fn ($q) => $q->where('l.loan_number', 'like', $search)->orWhere('l.borrower_name', 'like', $search)->orWhere('l.identity_number', 'like', $search));
        if (in_array($this->status, ['active', 'partially_resolved', 'closed', 'voided', 'integrity_error'], true)) {
            $query->whereRaw("COALESCE(p.progress,'integrity_error') = ?", [$this->status]);
        }
        if ($this->status === 'overdue') {
            $query->where('p.open_units', '>', 0)->where('l.due_date', '<', now('Asia/Jakarta')->toDateString());
        }
        if ($this->status === 'open') {
            $query->whereIn('p.progress', ['active', 'partially_resolved']);
        }
        if (in_array($this->borrowerType, ['student', 'lecturer', 'staff'], true)) {
            $query->where('l.borrower_type', $this->borrowerType);
        }

        return view('livewire.admin.equipment-loans.index', ['loans' => $query->orderByDesc('l.id')->paginate(in_array($this->perPage, [10, 25, 50], true) ? $this->perPage : 10, ['*'], 'loansPage')]);
    }
}
