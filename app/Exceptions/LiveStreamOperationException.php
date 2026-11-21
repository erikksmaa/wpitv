<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Psr\Log\LoggerInterface;
use RuntimeException;

final class LiveStreamOperationException extends RuntimeException
{
    public function __construct(public readonly string $errorCode)
    {
        $message = match ($errorCode) {
            'LIVE_DUPLICATE' => 'Agenda, video YouTube, atau arsip sudah digunakan.',
            'LIVE_STALE' => 'Data telah berubah. Muat ulang sebelum mencoba kembali.',
            'LIVE_CONCURRENCY' => 'Data sedang diproses petugas lain. Muat ulang lalu coba kembali.',
            'LIVE_STATE' => 'Perubahan tidak sesuai status atau relasi siaran. Periksa kembali data.',
            'LIVE_DEPENDENCY' => 'Agenda tidak dapat dihapus karena terhubung dengan Live Streaming. Gunakan pembatalan atau tarik publikasi untuk mempertahankan histori.',
            'LIVE_COORDINATION' => 'Agenda terhubung dengan Live Streaming. Muat ulang form dan simpan melalui alur lifecycle dengan versi terbaru.',
            'LIVE_SCHEMA' => 'Konfigurasi tabel Live Streaming belum lengkap. Hubungi administrator sebelum mengubah Agenda.',
            default => 'Operasi live streaming gagal. Silakan coba lagi.',
        };
        parent::__construct($message.' ['.$errorCode.']');
    }

    public function report(LoggerInterface $logger): void
    {
        $logger->warning('Live streaming operation failed.', ['error_code' => $this->errorCode]);
    }

    public function isConflict(): bool
    {
        return in_array($this->errorCode, ['LIVE_DUPLICATE', 'LIVE_STALE', 'LIVE_CONCURRENCY', 'LIVE_STATE', 'LIVE_DEPENDENCY', 'LIVE_COORDINATION'], true);
    }

    public function render(Request $request): JsonResponse|Response
    {
        $status = $this->isConflict() ? 409 : 503;
        $headers = ['Cache-Control' => 'private, no-store'];

        return $request->expectsJson()
            ? new JsonResponse(['message' => $this->getMessage(), 'error_code' => $this->errorCode], $status, $headers)
            : new Response($this->getMessage(), $status, [...$headers, 'Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
