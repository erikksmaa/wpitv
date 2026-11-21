<?php

namespace App\Livewire\Admin\EquipmentLoans;

use App\Livewire\Concerns\HandlesEquipmentErrors;
use App\Models\EquipmentLoan;
use App\Services\EquipmentLoanCorrection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.admin')]
class CorrectionForm extends Component
{
    use AuthorizesRequests;
    use HandlesEquipmentErrors;

    #[Locked]
    public int $loanId;

    #[Locked]
    public int $expectedVersion;

    #[Locked]
    public string $operationToken;

    public array $data = ['borrower_name' => '', 'student_number' => '', 'study_program' => '', 'whatsapp_number' => '', 'notes' => ''];

    public string $reason = '';

    public function mount(EquipmentLoan $loan): void
    {
        $this->authorize('correct', $loan);
        $this->loanId = $loan->id;
        $this->expectedVersion = $loan->version;
        $this->operationToken = (string) Str::uuid();
        foreach (array_keys($this->data) as $field) {
            $this->data[$field] = $loan->{$field} ?? '';
        }
    }

    public function save(EquipmentLoanCorrection $service)
    {
        $this->authorize('correct', EquipmentLoan::findOrFail($this->loanId));
        $service->record(auth()->user(), $this->operationToken, [
            ...$this->data, 'loan_id' => $this->loanId, 'expected_version' => $this->expectedVersion, 'reason' => $this->reason,
        ]);
        session()->flash('success', 'Koreksi identitas dan catatan berhasil disimpan.');

        return redirect()->route('admin.equipment-loans.show', $this->loanId);
    }

    public function render()
    {
        $loan = EquipmentLoan::findOrFail($this->loanId);
        $this->authorize('correct', $loan);

        // Do not refill data or replace the token/version on render or retry.
        return view('livewire.admin.equipment-loans.correction-form', compact('loan'))->title('Koreksi Peminjaman');
    }
}
