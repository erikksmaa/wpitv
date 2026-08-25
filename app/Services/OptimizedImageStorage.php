<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class OptimizedImageStorage
{
    /**
     * Resize an uploaded raster image and store it as a compact WebP file.
     */
    public function store(
        UploadedFile $file,
        string $directory,
        string $baseName,
        int $maxWidth,
        int $maxHeight,
        int $quality = 82,
    ): string {
        $sourceData = file_get_contents($file->getRealPath());
        $source = $sourceData !== false ? @imagecreatefromstring($sourceData) : false;

        if ($source === false) {
            throw new RuntimeException('Gambar tidak dapat diproses. Gunakan berkas JPG, PNG, atau WebP yang valid.');
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $scale = min(1, $maxWidth / $sourceWidth, $maxHeight / $sourceHeight);
        $targetWidth = max(1, (int) round($sourceWidth * $scale));
        $targetHeight = max(1, (int) round($sourceHeight * $scale));
        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);

        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, $transparent);
        imagecopyresampled(
            $canvas,
            $source,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $sourceWidth,
            $sourceHeight,
        );

        ob_start();
        $encoded = imagewebp($canvas, null, max(1, min(100, $quality)));
        $webpData = ob_get_clean();
        imagedestroy($source);
        imagedestroy($canvas);

        if (! $encoded || ! is_string($webpData) || $webpData === '') {
            throw new RuntimeException('Gambar gagal dikompresi ke format WebP.');
        }

        $filename = Str::slug($baseName).'-'.time().'-'.Str::lower(Str::random(6)).'.webp';
        Storage::disk('public')->put(trim($directory, '/').'/'.$filename, $webpData);

        return $filename;
    }
}
