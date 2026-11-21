<?php

namespace App\Http\Controllers;

use App\Models\EquipmentLoan;
use App\Models\InventoryUnit;
use App\Services\EquipmentStructure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

final class EquipmentPhotoController
{
    public function unit(InventoryUnit $unit, int $photo)
    {
        Gate::authorize('view', $unit);

        if (request()->boolean('cover')) {
            abort_unless(EquipmentStructure::installed() && $unit->item_id === $unit->id, 404);

            return $this->serve(DB::table('inventory_item_photos')->where('item_id', $unit->item_id)->find($photo));
        }

        return $this->serve(DB::table('inventory_unit_photos')->where('unit_id', $unit->id)->find($photo));
    }

    public function evidence(EquipmentLoan $loan, int $receipt, int $item, int $photo)
    {
        Gate::authorize('view', $loan);
        $query = DB::table('equipment_return_photos as p')->join('equipment_audit_events as a', 'a.id', '=', 'p.receipt_id')
            ->join('equipment_return_photo_items as link', 'link.photo_id', '=', 'p.id')->join('equipment_loan_items as i', 'i.id', '=', 'link.loan_item_id')
            ->where('a.loan_id', $loan->id)->where('i.loan_id', $loan->id)->where('a.event_type', 'returned')->where('a.id', $receipt)->where('i.id', $item)->where('p.id', $photo)->select('p.*');
        if (EquipmentStructure::installed()) {
            $query->where(fn ($q) => $q->whereNull('p.return_receipt_id')->orWhereExists(fn ($details) => $details
                ->selectRaw('1')->from('equipment_return_items as detail')
                ->join('equipment_return_receipts as receipt', 'receipt.id', '=', 'detail.receipt_id')
                ->whereColumn('receipt.id', 'p.return_receipt_id')
                ->whereColumn('receipt.audit_event_id', 'a.id')
                ->whereColumn('receipt.loan_id', 'i.loan_id')
                ->whereColumn('detail.loan_item_id', 'i.id')));
        }

        return $this->serve($query->first());
    }

    private function serve(?object $file)
    {
        abort_unless($file && preg_match('~\Aequipment/[a-f0-9-]{36}\z~D', $file->storage_key), 404);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($file->storage_key), 404);
        $ext = match ($file->mime) {
            'image/jpeg' => 'jpg','image/webp' => 'webp','image/png' => 'png',default => abort(404)
        };

        return $disk->response($file->storage_key, 'foto.'.$ext, ['Content-Type' => $file->mime, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'], request()->boolean('download') ? 'attachment' : 'inline');
    }
}
