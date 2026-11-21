<?php

namespace App\Livewire\Admin\EquipmentLoans;

use App\Livewire\Concerns\HandlesEquipmentErrors;
use App\Models\EquipmentLoan;
use App\Services\EquipmentPhotos;
use App\Services\EquipmentStructure;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.admin')]
class Show extends Component
{
    use AuthorizesRequests;
    use HandlesEquipmentErrors;

    #[Locked]
    public int $loanId;

    public function mount(EquipmentLoan $loan): void
    {
        $this->authorize('view', $loan);
        $this->loanId = $loan->id;
    }

    public function render()
    {
        $loan = EquipmentLoan::findOrFail($this->loanId);
        $this->authorize('view', $loan);
        $loan->load('items');
        // Operational due-date history only; do not expose correction PII or
        // token/hash metadata through this view to Production.
        $extensions = $loan->auditEvents()->where('event_type', 'extended')->orderByDesc('id')->limit(10)
            ->get(['id', 'reason', 'actor_name', 'before_data', 'after_data']);

        $timeline = $loan->auditEvents()->whereIn('event_type', ['checkout', 'returned', 'lost'])->latest('id')->get(['id', 'event_type', 'created_at', 'actor_name']);
        $evidence = ! EquipmentPhotos::enabled() ? collect() : DB::table('equipment_return_photos as p')->join('equipment_audit_events as a', 'a.id', '=', 'p.receipt_id')->join('equipment_return_photo_items as l', 'l.photo_id', '=', 'p.id')->join('equipment_loan_items as i', 'i.id', '=', 'l.loan_item_id')->where('a.loan_id', $loan->id)->where('i.loan_id', $loan->id)->get(['p.id', 'p.receipt_id', 'i.id as item_id', 'i.condition_in', 'i.lost_at'])->groupBy('receipt_id');

        $receipts = EquipmentStructure::installed()
            ? DB::table('equipment_return_receipts')->where('loan_id', $loan->id)->pluck('receipt_code', 'audit_event_id')
            : collect();

        return view('livewire.admin.equipment-loans.show', compact('loan', 'extensions', 'timeline', 'evidence', 'receipts'))->title('Detail Peminjaman');
    }
}
