<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

final class EquipmentPhotoController extends Controller
{
    public function show(string $kind, int $photo)
    {
        Gate::authorize('equipment.inventory.view');
        Gate::authorize('equipment.photo.view');
        abort_unless(in_array($kind, ['model', 'unit'], true), 404);
        $row = DB::table($kind === 'model' ? 'equipment_model_photos' : 'equipment_unit_photos')->where('id', $photo)->first();
        abort_unless($row, 404);
        $parent = DB::table($kind === 'model' ? 'equipment_models' : 'equipment_units')->where('id', $row->{$kind.'_id'})->exists();
        abort_unless($parent && preg_match('~\Aequipment/photos/[a-f0-9-]{36}\.png\z~', $row->storage_path), 404);
        abort_unless(Storage::disk('local')->exists($row->storage_path), 404);

        return Storage::disk('local')->response($row->storage_path, 'equipment-photo.png', [
            'Content-Type' => 'image/png', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
        ]);
    }
}
