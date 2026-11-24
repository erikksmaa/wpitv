<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

final class EquipmentReturnPhotoController extends Controller
{
    public function show(int $loan, int $photo)
    {
        Gate::authorize('equipment.loan.view');
        Gate::authorize('equipment.photo.view');
        $row = DB::table('equipment_return_photos as p')->join('equipment_returns as r', 'r.id', '=', 'p.return_id')
            ->join('equipment_loan_units as lu', fn ($q) => $q->on('lu.id', '=', 'p.loan_unit_id')->on('lu.return_id', '=', 'p.return_id')->on('lu.loan_id', '=', 'r.loan_id'))
            ->where('r.loan_id', $loan)->where('p.id', $photo)->where('lu.outcome', 'returned')->select('p.*')->first();
        abort_unless($row && preg_match('~\Aequipment/photos/[a-f0-9-]{36}\.png\z~', $row->storage_path), 404);
        abort_unless(Storage::disk('local')->exists($row->storage_path), 404);

        return Storage::disk('local')->response($row->storage_path, 'bukti-retur.png', [
            'Content-Type' => 'image/png', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
        ]);
    }
}
