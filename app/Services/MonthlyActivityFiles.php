<?php

namespace App\Services;

use App\Exceptions\MonthlyActivityException;
use App\Models\MonthlyActivityAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class MonthlyActivityFiles
{
    public const MAX_BYTES = 10 * 1024 * 1024;

    private const TYPES = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'pdf' => 'application/pdf'];

    public function stage(UploadedFile $upload, int $reportId): array
    {
        $extension = strtolower($upload->getClientOriginalExtension());
        $path = $upload->getRealPath();
        $size = $upload->getSize();
        if (! $upload->isValid() || ! $path || ! $size || $size > self::MAX_BYTES || ! isset(self::TYPES[$extension])) {
            throw ValidationException::withMessages(['attachment' => 'Pilih JPEG, PNG, atau PDF berukuran maksimal 10 MB.']);
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if ($mime !== self::TYPES[$extension] || ! $this->validSignature($path, $size, $mime)) {
            throw ValidationException::withMessages(['attachment' => 'Isi file tidak sesuai dengan format JPEG, PNG, atau PDF.']);
        }

        $token = bin2hex(random_bytes(24));
        $prefix = 'monthly-activity/'.$reportId.'/';
        $temporary = $prefix.$token.'.stage';
        $key = $prefix.$token.'.'.$extension;
        $digest = hash_file('sha256', $path);
        $stream = @fopen($path, 'rb');
        try {
            $disk = Storage::disk('local');
            if (! $digest || ! $stream || ! $disk->put($temporary, $stream, 'private') || ! $disk->move($temporary, $key)) {
                throw new MonthlyActivityException('MONTHLY_FILE');
            }
        } catch (Throwable) {
            $this->discard($temporary);
            $this->discard($key);
            throw new MonthlyActivityException('MONTHLY_FILE');
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $name = str_replace('\\', '/', $upload->getClientOriginalName());

        return [
            'display_name' => self::safeName(pathinfo(basename($name), PATHINFO_FILENAME), $extension),
            'disk' => 'local', 'storage_key' => $key, 'mime_type' => $mime,
            'size_bytes' => $size, 'sha256' => $digest, 'status' => 'ready',
        ];
    }

    public function discard(string $key): void
    {
        if (! preg_match('~\Amonthly-activity/[1-9][0-9]*/[a-f0-9]{48}\.(jpg|jpeg|png|pdf|stage)\z~', $key)) {
            return;
        }
        try {
            if (Storage::disk('local')->delete($key)) {
                return;
            }
        } catch (Throwable) {
            // Log a stable code without the storage key or exception context.
        }

        logger()->warning('MONTHLY_FILE_CLEANUP');
    }

    public function readable(MonthlyActivityAttachment $file): bool
    {
        return $this->path($file) !== null;
    }

    /** @return resource|null */
    public function open(MonthlyActivityAttachment $file)
    {
        $path = $this->path($file);
        $stream = $path === null ? false : @fopen($path, 'rb');
        if (! $stream) {
            return null;
        }
        $hash = hash_init('sha256');
        $bytes = hash_update_stream($hash, $stream, self::MAX_BYTES + 1);
        if ($bytes !== $file->size_bytes || $bytes > self::MAX_BYTES || ! hash_equals($file->sha256, hash_final($hash)) || ! rewind($stream)) {
            fclose($stream);

            return null;
        }

        return $stream;
    }

    public function downloadName(MonthlyActivityAttachment $file): string
    {
        return self::safeName(pathinfo($file->display_name, PATHINFO_FILENAME), pathinfo($file->storage_key, PATHINFO_EXTENSION));
    }

    private function path(MonthlyActivityAttachment $file): ?string
    {
        $prefix = 'monthly-activity/'.$file->report_id.'/';
        $key = $file->storage_key;
        $extension = pathinfo($key, PATHINFO_EXTENSION);
        if ($file->disk !== 'local' || $file->status !== 'ready' || (self::TYPES[$extension] ?? null) !== $file->mime_type
            || ! preg_match('~\A'.preg_quote($prefix, '~').'[a-f0-9]{48}\.(jpg|jpeg|png|pdf)\z~', $key)) {
            return null;
        }

        try {
            $disk = Storage::disk('local');
            $root = realpath($disk->path(''));
            $path = realpath($disk->path($key));
        } catch (Throwable) {
            return null;
        }
        if (! $root || ! $path || ! str_starts_with($path, rtrim($root, '/\\').DIRECTORY_SEPARATOR) || ! is_file($path) || ! is_readable($path)) {
            return null;
        }

        return $path;
    }

    private static function safeName(string $stem, string $extension): string
    {
        $stem = trim(preg_replace('/[^a-zA-Z0-9_-]+/', '-', $stem), '-_');

        return (substr($stem, 0, 120) ?: 'lampiran').'.'.$extension;
    }

    private function validSignature(string $path, int $size, string $mime): bool
    {
        $head = @file_get_contents($path, false, null, 0, 16);
        $tail = @file_get_contents($path, false, null, max(0, $size - 1024), 1024);
        if ($head === false || $tail === false) {
            return false;
        }

        return match ($mime) {
            'image/jpeg' => str_starts_with($head, "\xFF\xD8\xFF") && str_ends_with($tail, "\xFF\xD9") && (@getimagesize($path)[2] ?? null) === IMAGETYPE_JPEG,
            'image/png' => str_starts_with($head, "\x89PNG\x0D\x0A\x1A\x0A") && str_ends_with($tail, "\x00\x00\x00\x00IEND\xAE\x42\x60\x82") && (@getimagesize($path)[2] ?? null) === IMAGETYPE_PNG,
            'application/pdf' => preg_match('/\A%PDF-[12]\.[0-9][\r\n]/', $head) && preg_match('/%%EOF[\x00\x09\x0A\x0C\x0D\x20]*\z/', $tail),
            default => false,
        };
    }
}
