<?php

namespace App\Services\Equipment;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use League\Flysystem\FilesystemException;

final class InventoryPhotos
{
    public function prepare(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        if (! $file->isValid() || ! $path || $file->getSize() < 1) {
            throw ValidationException::withMessages(['photos' => 'Foto gagal diunggah. Pilih kembali filenya.']);
        }
        if ($file->getSize() > 5000000) {
            throw ValidationException::withMessages(['photos' => 'Ukuran foto maksimal 5 MB.']);
        }
        $info = @getimagesize($path);
        if (! $info || ! in_array($info['mime'], ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw ValidationException::withMessages(['photos' => 'Format foto tidak didukung. Gunakan JPG, PNG, atau WebP.']);
        }
        if (min($info[0], $info[1]) < 64 || max($info[0], $info[1]) > 4096 || $info[0] * $info[1] > 16777216) {
            throw ValidationException::withMessages(['photos' => 'Lebar dan tinggi foto harus antara 64 dan 4096 piksel.']);
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
            throw ValidationException::withMessages(['photos' => 'Foto melebihi 5 MB setelah diproses. Pilih foto yang lebih kecil.']);
        }

        return ['bytes' => $bytes, 'sha256' => hash('sha256', $bytes), 'file_size' => strlen($bytes), 'mime_type' => 'image/png'];
    }

    public function store(array $photo): array
    {
        $path = 'equipment/photos/'.Str::uuid().'.png';
        try {
            if (! Storage::disk('local')->put($path, $photo['bytes'], ['visibility' => 'private'])) {
                throw new \RuntimeException('Equipment private photo write returned false.');
            }
        } catch (FilesystemException|\RuntimeException $error) {
            report($error);
            throw ValidationException::withMessages(['photos' => 'Foto gagal disimpan. Silakan coba lagi.']);
        }

        return ['storage_path' => $path, 'mime_type' => $photo['mime_type'], 'file_size' => $photo['file_size'], 'sha256' => $photo['sha256']];
    }
}
