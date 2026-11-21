<?php

namespace App\Exceptions;

use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use PDOException;
use RuntimeException;
use Throwable;

final class MonthlyActivityException extends RuntimeException
{
    public static function forHandler(Throwable $exception, ?Request $request): Throwable
    {
        for ($cause = $exception; $cause !== null; $cause = $cause->getPrevious()) {
            if ($cause instanceof PDOException && ($request?->routeIs('admin.monthly-reports.*')
                || ($cause instanceof QueryException && preg_match('/\bmonthly_activity_(reports|items|attachments|versions|events)\b/i', $cause->getSql())))) {
                return new self('MONTHLY_FAILURE');
            }
        }

        return $exception;
    }

    public function __construct(public readonly string $errorCode)
    {
        parent::__construct(match ($errorCode) {
            'MONTHLY_DUPLICATE' => 'Laporan divisi pada periode ini sudah tersedia.',
            'MONTHLY_STALE' => 'Laporan telah berubah. Muat ulang sebelum menyimpan.',
            'MONTHLY_STATE' => 'Aksi tidak tersedia pada status laporan ini.',
            'MONTHLY_CONCURRENCY' => 'Laporan sedang diproses. Muat ulang lalu coba kembali.',
            'MONTHLY_FILE' => 'File tidak dapat disimpan atau dibaca. Silakan coba kembali.',
            default => 'Operasi laporan bulanan gagal. Silakan coba kembali.',
        });
    }
}
