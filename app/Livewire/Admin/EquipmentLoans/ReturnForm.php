<?php

namespace App\Livewire\Admin\EquipmentLoans;

use App\Livewire\Concerns\HandlesEquipmentErrors;
use App\Models\EquipmentLoan;
use App\Services\EquipmentLoanReturn;
use App\Services\EquipmentPhotos;
use App\Services\EquipmentQuantity;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Renderless;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.admin')]
class ReturnForm extends Component
{
    use WithFileUploads {
        _startUpload as private beginFileUpload;
        _finishUpload as private finishFileUpload;
    }

    private function authorizeUpload(string $name): void
    {
        $this->authorize('recordReturn', EquipmentLoan::findOrFail($this->loanId));
        abort_unless(preg_match('/\Aphotos\.([0-9]+)\z/', $name, $match) && in_array((int) $match[1], $this->initialItemIds, true), 403);
    }

    #[Renderless]
    public function _startUpload($name, $fileInfo, $isMultiple)
    {
        $this->authorizeUpload($name);
        abort_if($isMultiple, 422);
        $this->beginFileUpload($name, $fileInfo, false);
    }

    public function _finishUpload($name, $tmpPath, $isMultiple, $append = true)
    {
        $this->authorizeUpload($name);
        abort_if($isMultiple, 422);
        $this->finishFileUpload($name, $tmpPath, false, $append);
    }

    public array $photos = [];

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

    public array $returns = [];

    public function mount(EquipmentLoan $loan): void
    {
        $this->authorize('recordReturn', $loan);
        $this->loanId = $loan->id;
        $this->expectedVersion = $loan->version;
        $this->operationToken = (string) Str::uuid();
        $this->initialItemIds = $loan->items()->whereNull('returned_at')->orderBy('id')->pluck('id')->all();
        // Preserve these values across retries; do not recompute now() on save.
        $returnedAt = now('Asia/Jakarta')->format('Y-m-d\TH:i:s');
        foreach ($this->initialItemIds as $id) {
            $this->returns[$id] = ['returned_at' => $returnedAt, 'condition_in' => '', 'return_notes' => ''];
        }
    }

    public int $quantity = 1;

    public function selectQuantity(): void
    {
        $loan = EquipmentLoan::findOrFail($this->loanId);
        $this->authorize('recordReturn', $loan);
        $this->validate(['quantity' => 'required|integer|min:1|max:10']);
        $ids = $loan->items()->whereIn('id', $this->initialItemIds)->whereNull('returned_at')->orderBy('id')->limit($this->quantity)->pluck('id');
        if ($ids->count() !== $this->quantity) {
            $this->addError('quantity', 'Jumlah melebihi sisa pinjaman.');

            return;
        }
        $this->selectedIds = array_map('strval', $ids->all());
    }

    public function selectAll(): void
    {
        $this->authorize('recordReturn', EquipmentLoan::findOrFail($this->loanId));
        $this->selectedIds = array_map('strval', array_slice($this->initialItemIds, 0, EquipmentPhotos::enabled() ? 10 : 50));
    }

    public function save(EquipmentLoanReturn $service)
    {
        $loan = EquipmentLoan::findOrFail($this->loanId);
        $this->authorize('recordReturn', $loan);
        $this->validate([
            'selectedIds' => ['required', 'array', 'min:1', EquipmentPhotos::enabled() ? 'max:10' : 'max:50'],
            'selectedIds.*' => ['required', 'integer', 'min:1', 'distinct'],
            'returns' => ['required', 'array'],
        ], [], ['selectedIds' => 'unit yang dikembalikan']);

        $items = [];
        foreach ($this->selectedIds as $id) {
            $fields = $this->returns[$id] ?? [];
            if (! is_array($fields)) {
                $fields = [];
            }
            $items[] = [
                'item_id' => (int) $id,
                'returned_at' => $fields['returned_at'] ?? null,
                'condition_in' => $fields['condition_in'] ?? null,
                'return_notes' => $fields['return_notes'] ?? null,
            ];
        }
        $files = app(EquipmentPhotos::class);
        $prepared = [];
        try {
            foreach ($files::enabled() ? $this->selectedIds : [] as $id) {
                $this->validate(["photos.$id" => ['required', 'file', 'max:5120']]);
                $prepared[$id] = $files->stage($this->photos[$id]);
            }
            $service->record(auth()->user(), $this->operationToken, [
                'loan_id' => $this->loanId,
                'expected_version' => $this->expectedVersion,
                'items' => $items,
            ], $prepared);
        } finally {
            $files->discardUnused('equipment_return_photos', $prepared);
        }
        session()->flash('success', 'Pengembalian alat berhasil dicatat.');

        return redirect()->route('admin.equipment-loans.show', $this->loanId);
    }

    public function render()
    {
        $loan = EquipmentLoan::withCount(['items', 'items as returned_count' => fn ($q) => $q->whereNotNull('returned_at')])->findOrFail($this->loanId);
        $this->authorize('recordReturn', $loan);
        // Keep the original batch rows available for an exact retry, even when
        // another request already returned an item. The service checks the receipt.
        $items = $loan->items()->whereIn('id', $this->initialItemIds)->orderBy('id')->get();

        $photoEnabled = EquipmentPhotos::enabled();
        $unitPhotos = app(EquipmentQuantity::class)->photos($items->pluck('inventory_unit_id'));

        return view('livewire.admin.equipment-loans.return-form', compact('loan', 'items', 'unitPhotos', 'photoEnabled'))->title('Pengembalian Alat');
    }
}
