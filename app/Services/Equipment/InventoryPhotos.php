<?php

namespace App\Services\Equipment;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class InventoryPhotos
{
    public function prepare(UploadedFile $file): array
    {
        $info = @getimagesize($file->getRealPath());
        if (! $file->isValid() || $file->getSize() < 1 || $file->getSize() > 5000000 || ! $info
            || ! in_array($info['mime'], ['image/jpeg', 'image/png', 'image/webp'], true)
            || min($info[0], $info[1]) < 64 || max($info[0], $info[1]) > 4096 || $info[0] * $info[1] > 16777216) {
            throw ValidationException::withMessages(['photos' => 'Foto harus JPG/PNG/WebP valid, 64–4096 px dan maksimal 5.000.000 byte.']);
        }
        $image = @imagecreatefromstring(file_get_contents($file->getRealPath()));
        if (! $image) {
            throw ValidationException::withMessages(['photos' => 'Foto tidak dapat dibaca.']);
        }
        if ($info['mime'] === 'image/jpeg' && function_exists('exif_read_data')) {
            $exif = @exif_read_data($file->getRealPath());
            $orientation = (int) ($exif['Orientation'] ?? 1);
            if (in_array($orientation, [2, 4, 5, 7], true)) {
                imageflip($image, in_array($orientation, [2, 5, 7], true) ? IMG_FLIP_HORIZONTAL : IMG_FLIP_VERTICAL);
            }
            $angle = match ($orientation) {
                3 => 180, 5, 6 => -90, 7, 8 => 90, default => 0
            };
            if ($angle) {
                $rotated = imagerotate($image, $angle, 0);
                imagedestroy($image);
                $image = $rotated;
            }
        }
        ob_start();
        imagepng($image, null, 6);
        $bytes = ob_get_clean();
        imagedestroy($image);
        if (strlen($bytes) > 5000000) {
            throw ValidationException::withMessages(['photos' => 'Hasil normalisasi melebihi 5.000.000 byte.']);
        }

        return ['bytes' => $bytes, 'sha256' => hash('sha256', $bytes), 'file_size' => strlen($bytes), 'mime_type' => 'image/png'];
    }

    public function store(array $photo): array
    {
        $path = 'equipment/photos/'.Str::uuid().'.png';
        if (! Storage::disk('local')->put($path, $photo['bytes'], ['visibility' => 'private'])) {
            throw new \RuntimeException('Penyimpanan foto gagal; transaksi dibatalkan.');
        }

        return ['storage_path' => $path, 'mime_type' => $photo['mime_type'], 'file_size' => $photo['file_size'], 'sha256' => $photo['sha256']];
    }
}
