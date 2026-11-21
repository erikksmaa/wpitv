<?php

namespace App\Livewire\Admin\EquipmentLoans;

use App\Livewire\Concerns\HandlesEquipmentErrors;
use App\Models\EquipmentLoan;
use App\Services\EquipmentLoanLoss;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.admin')]
class LossForm extends Component
{
    use AuthorizesRequests;
    use HandlesEquipmentErrors;

    #[Locked]
    public int $loanId;

    #[Locked]
    public int $expectedVersion;

    #[Locked]
    public string $operationToken;

    #[Locked]
    public array $initialItemIds = [];

    public array $selectedIds = [];

    public array $losses = [];

    public function mount(EquipmentLoan $loan): void
    {
        $this->authorize('reportLoss', $loan);
        $this->loanId = $loan->id;
        $this->expectedVersion = $loan->version;
        $this->operationToken = (string) Str::uuid();
        $this->initialItemIds = $loan->items()->whereNull('returned_at')->whereNull('lost_at')->orderBy('id')->pluck('id')->all();
        // Preserve these values across retries; do not recompute now() on save.
        $lostAt = now('Asia/Jakarta')->format('Y-m-d\TH:i:s');
        foreach ($this->initialItemIds as $id) {
            $this->losses[$id] = ['lost_at' => $lostAt, 'loss_notes' => ''];
        }
    }

    public function selectAll(): void
    {
        $this->authorize('reportLoss', EquipmentLoan::findOrFail($this->loanId));
        $this->selectedIds = array_map('strval', $this->initialItemIds);
    }

    public function save(EquipmentLoanLoss $service)
    {
        $loan = EquipmentLoan::findOrFail($this->loanId);
        $this->authorize('reportLoss', $loan);
        $this->validate([
            'selectedIds' => ['required', 'array', 'min:1', 'max:50'],
            'selectedIds.*' => ['required', 'integer', 'min:1', 'distinct'],
            'losses' => ['required', 'array'],
        ], [], ['selectedIds' => 'unit yang dilaporkan hilang']);

        $items = [];
        foreach ($this->selectedIds as $id) {
            $fields = $this->losses[$id] ?? [];
            if (! is_array($fields)) {
                $fields = [];
            }
            $items[] = [
                'item_id' => (int) $id,
                'lost_at' => $fields['lost_at'] ?? null,
                'loss_notes' => $fields['loss_notes'] ?? null,
            ];
        }
        $service->record(auth()->user(), $this->operationToken, [
            'loan_id' => $this->loanId,
            'expected_version' => $this->expectedVersion,
            'items' => $items,
        ]);
        session()->flash('success', 'Kehilangan alat berhasil dicatat. Unit tetap terkunci sampai dikembalikan.');

        return redirect()->route('admin.equipment-loans.show', $this->loanId);
    }

    public function render()
    {
        $loan = EquipmentLoan::findOrFail($this->loanId);
        $this->authorize('reportLoss', $loan);
        // Keep the original batch rows available for an exact retry, even when
        // another request already reported the loss or returned an item. The service checks the receipt.
        $items = $loan->items()->whereIn('id', $this->initialItemIds)->orderBy('id')->get();

        return view('livewire.admin.equipment-loans.loss-form', compact('loan', 'items'))->title('Laporan Kehilangan Alat');
    }
}
