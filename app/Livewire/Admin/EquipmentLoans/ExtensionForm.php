<?php

namespace App\Livewire\Admin\EquipmentLoans;

use App\Livewire\Concerns\HandlesEquipmentErrors;
use App\Models\EquipmentLoan;
use App\Services\EquipmentLoanExtension;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.admin')]
class ExtensionForm extends Component
{
    use AuthorizesRequests;
    use HandlesEquipmentErrors;

    #[Locked]
    public int $loanId;

    #[Locked]
    public int $expectedVersion;

    #[Locked]
    public string $operationToken;

    public string $newDueAt = '';

    public string $reason = '';

    public function mount(EquipmentLoan $loan): void
    {
        $this->authorize('extend', $loan);
        $this->loanId = $loan->id;
        $this->expectedVersion = $loan->version;
        $this->operationToken = (string) Str::uuid();
    }

    public function save(EquipmentLoanExtension $service)
    {
        $this->authorize('extend', EquipmentLoan::findOrFail($this->loanId));
        $service->record(auth()->user(), $this->operationToken, [
            'loan_id' => $this->loanId, 'expected_version' => $this->expectedVersion,
            'new_due_at' => $this->newDueAt, 'reason' => $this->reason,
        ]);
        session()->flash('success', 'Jatuh tempo peminjaman berhasil diperpanjang.');

        return redirect()->route('admin.equipment-loans.show', $this->loanId);
    }

    public function render()
    {
        $loan = EquipmentLoan::findOrFail($this->loanId);
        $this->authorize('extend', $loan);
        $hasOpenItems = $loan->items()->whereNull('returned_at')->exists();
        $hasUnresolvedLoss = $loan->items()->whereNull('returned_at')->whereNotNull('lost_at')->exists();

        return view('livewire.admin.equipment-loans.extension-form', compact('loan', 'hasOpenItems', 'hasUnresolvedLoss'))->title('Perpanjangan Peminjaman');
    }
}
