<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class EquipmentPhotos
{
    public static function enabled(): bool
    {
        $count = DB::table('information_schema.tables')->whereRaw('table_schema = DATABASE()')->whereIn('table_name', ['inventory_unit_photos', 'equipment_return_photos', 'equipment_return_photo_items'])->count();
        if ($count !== 0 && $count !== 3) {
            throw ValidationException::withMessages(['photos' => 'Konfigurasi tabel foto belum lengkap.']);
        }

        return $count === 3;
    }

    public function stage(UploadedFile $file): array
    {
        $types = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
        $mime = $types[strtolower($file->getClientOriginalExtension())] ?? null;
        $info = @getimagesize($file->getRealPath());
        if (! $mime || ! $file->isValid() || $file->getSize() > 5242880 || ! $info || $info['mime'] !== $mime || $file->getMimeType() !== $mime || $info[0] * $info[1] > 20000000) {
            throw ValidationException::withMessages(['photos' => 'Gunakan JPEG, PNG atau WebP hingga 5 MiB dan 20 megapiksel.']);
        }
        $image = @imagecreatefromstring(file_get_contents($file->getRealPath()));
        if (! $image) {
            throw ValidationException::withMessages(['photos' => 'Foto tidak dapat dibaca.']);
        }
        ob_start();
        try {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            $ok = match ($mime) {
                'image/jpeg' => imagejpeg($image, null, 90),
                'image/png' => imagepng($image),
                'image/webp' => imagewebp($image, null, 90),
            };
            $bytes = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        if (! $ok || ! $bytes || strlen($bytes) > 5242880) {
            throw ValidationException::withMessages(['photos' => 'Ukuran foto hasil validasi melebihi batas atau foto rusak.']);
        }
        $key = 'equipment/'.Str::uuid();
        if (! Storage::disk('local')->put($key, $bytes, 'private')) {
            throw ValidationException::withMessages(['photos' => 'Foto gagal disimpan.']);
        }

        return ['storage_key' => $key, 'mime' => $mime, 'byte_size' => strlen($bytes), 'sha256' => hash('sha256', $bytes), 'created_at' => now('UTC')->format('Y-m-d H:i:s.u')];
    }

    public function discardUnused(string $table, array $photos): void
    {
        foreach ($photos as $photo) {
            if (! DB::table($table)->useWritePdo()->where('storage_key', $photo['storage_key'])->exists()) {
                Storage::disk('local')->delete($photo['storage_key']);
            }
        }
    }
}
