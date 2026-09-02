<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class OptimizedImageStorage
{
    private const MAX_SOURCE_PIXELS = 25_000_000;

    private const MAX_SOURCE_DIMENSION = 10_000;

    public const PROGRAMS = 'programs';

    public const NEWS = 'news';

    public const TVCLUB_MEMBERS = 'tvclub/members';

    private const ALLOWED_DIRECTORIES = [
        self::PROGRAMS,
        self::NEWS,
        self::TVCLUB_MEMBERS,
    ];

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
        $directory = $this->validatedDirectory($directory);
        $sourceData = file_get_contents($file->getRealPath());
        $dimensions = $sourceData !== false ? @getimagesizefromstring($sourceData) : false;

        if (
            $dimensions === false
            || $dimensions[0] < 1
            || $dimensions[1] < 1
            || $dimensions[0] > self::MAX_SOURCE_DIMENSION
            || $dimensions[1] > self::MAX_SOURCE_DIMENSION
            || $dimensions[0] * $dimensions[1] > self::MAX_SOURCE_PIXELS
        ) {
            throw new RuntimeException('Dimensi gambar terlalu besar atau berkas gambar tidak valid.');
        }

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
        if (! Storage::disk('public')->put($directory.'/'.$filename, $webpData)) {
            throw new RuntimeException('Gambar gagal disimpan.');
        }

        return $filename;
    }

    /**
     * Delete a filename only when it is owned by the expected public directory.
     */
    public function deleteOwned(
        string $directory,
        ?string $storedFilename,
        ?string $resourceType = null,
        int|string|null $resourceId = null,
    ): bool {
        $directory = $this->validatedDirectory($directory);
        $path = $this->ownedPath($directory, $storedFilename);

        if ($storedFilename !== null && $storedFilename !== '' && $path === null) {
            Log::warning('Rejected unexpected stored image path.', array_filter([
                'resource' => $resourceType,
                'resource_id' => $resourceId,
            ], static fn ($value) => $value !== null));

            return false;
        }

        if ($path === null || ! Storage::disk('public')->exists($path)) {
            return false;
        }

        return Storage::disk('public')->delete($path);
    }

    /**
     * Resolve a database filename beneath its server-owned directory.
     */
    public function ownedPath(string $directory, ?string $storedFilename): ?string
    {
        $directory = $this->validatedDirectory($directory);

        if ($storedFilename === null || $storedFilename === '') {
            return null;
        }

        if (
            $storedFilename !== trim($storedFilename)
            || str_contains($storedFilename, '..')
            || str_contains($storedFilename, '/')
            || str_contains($storedFilename, '\\')
            || str_contains($storedFilename, ':')
            || preg_match('/[\x00-\x1F\x7F]/', $storedFilename) === 1
            || basename($storedFilename) !== $storedFilename
        ) {
            return null;
        }

        return $directory.'/'.$storedFilename;
    }

    private function validatedDirectory(string $directory): string
    {
        $directory = trim($directory, '/');

        if (! in_array($directory, self::ALLOWED_DIRECTORIES, true)) {
            throw new RuntimeException('Direktori penyimpanan gambar tidak diizinkan.');
        }

        return $directory;
    }
}
