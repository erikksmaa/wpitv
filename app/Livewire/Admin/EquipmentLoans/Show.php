<?php

namespace App\Livewire\Admin\EquipmentLoans;

use App\Services\Equipment\LoanService;
use App\Support\EquipmentPermissions;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.admin')]
#[Title('Detail Peminjaman Peralatan - WPiTV')]
class Show extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $loanId;

    #[Locked]
    public string $operation = '';

    #[Locked]
    public string $token = '';

    #[Locked]
    public int $version = 0;

    public array $selected = [];

    public array $items = [];

    public array $photos = [];

    public string $reason = '';

    public string $notes = '';

    public string $dueDate = '';

    public bool $notHandedOver = false;

    public function mount(int $loanId): void
    {
        Gate::authorize('equipment.loan.view');
        $this->loanId = $loanId;
        app(LoanService::class)->detail($loanId);
    }

    public function begin(string $operation): void
    {
        abort_unless(in_array($operation, ['return.create', 'loan.extend', 'loan.void', 'loan.loss'], true), 403);
        Gate::authorize('equipment.loan.view');
        Gate::authorize(EquipmentPermissions::OPERATIONS[$operation]);
        $detail = app(LoanService::class)->detail($this->loanId);
        $this->cancel();
        $this->operation = $operation;
        $this->token = (string) Str::uuid();
        $this->version = $detail['loan']->version;
        $this->dueDate = max(now('Asia/Jakarta')->toDateString(), CarbonImmutable::parse($detail['loan']->due_date, 'Asia/Jakarta')->addDay()->toDateString());
        foreach ($detail['loanUnits']->where('outcome', 'on_loan') as $unit) {
            $this->items[$unit->id] = ['condition' => 'good', 'notes' => ''];
        }
    }

    public function selectAll(): void
    {
        Gate::authorize(EquipmentPermissions::OPERATIONS[$this->operation] ?? 'equipment.unknown');
        abort_unless(in_array($this->operation, ['return.create', 'loan.loss'], true), 403);
        $ids = app(LoanService::class)->detail($this->loanId)['loanUnits']->where('outcome', 'on_loan')->pluck('id')->all();
        $this->selected = array_map('strval', array_slice($ids, 0, 10));
        $this->updatedSelected();
    }

    public function updatedSelected(): void
    {
        foreach (array_keys($this->photos) as $id) {
            if (! in_array((string) $id, array_map('strval', $this->selected), true)) {
                unset($this->photos[$id]);
            }
        }
    }

    public function cancel(): void
    {
        $this->resetValidation();
        $this->operation = '';
        $this->selected = [];
        $this->items = [];
        $this->photos = [];
        $this->reason = '';
        $this->notes = '';
        $this->notHandedOver = false;
    }

    public function save(LoanService $service): void
    {
        Gate::authorize('equipment.loan.view');
        Gate::authorize(EquipmentPermissions::OPERATIONS[$this->operation] ?? 'equipment.unknown');
        $data = ['loan_id' => $this->loanId, 'version' => $this->version];
        $uploads = [];
        if (in_array($this->operation, ['return.create', 'loan.loss'], true)) {
            $this->validate(['selected' => 'required|array|min:1|max:10', 'selected.*' => 'required|integer|min:1|distinct']);
            $data['items'] = [];
            foreach ($this->selected as $id) {
                $data['items'][] = ['loan_unit_id' => (int) $id, 'condition' => $this->items[$id]['condition'] ?? '', 'notes' => $this->items[$id]['notes'] ?? ''];
            }
            if ($this->operation === 'return.create') {
                $uploads = $this->photos;
                $data['notes'] = $this->notes;
            }
        }
        if ($this->operation === 'loan.extend') {
            $data['due_date'] = $this->dueDate;
        }
        if ($this->operation === 'loan.void') {
            $data['not_handed_over'] = $this->notHandedOver;
        }
        if ($this->operation !== 'return.create') {
            $data['reason'] = $this->reason;
        }
        $service->execute(auth()->user(), $this->operation, $this->token, $data, $uploads);
        $this->cancel();
        session()->flash('success', 'Tindakan berhasil dicatat beserta audit. Ketersediaan unit telah diperbarui.');
    }

    public function render(LoanService $service)
    {
        Gate::authorize('equipment.loan.view');

        return view('livewire.admin.equipment-loans.show', $service->detail($this->loanId));
    }
}
