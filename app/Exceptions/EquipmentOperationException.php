<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Psr\Log\LoggerInterface;
use RuntimeException;

/** A safe boundary: never retain the SQL exception, bindings or request data. */
final class EquipmentOperationException extends RuntimeException
{
    private function __construct(
        public readonly string $errorCode,
        public readonly ?string $field = null,
        ?string $message = null,
    ) {
        $message ??= match ($errorCode) {
            'EQ_DUPLICATE' => 'Data atau token berbenturan dengan transaksi lain. Muat ulang form.',
            'EQ_CONCURRENCY' => 'Transaksi sedang diproses petugas lain. Coba kirim ulang permintaan identik.',
            'EQ_STALE' => 'Data telah berubah. Muat ulang halaman sebelum mencoba kembali.',
            default => 'Operasi inventaris/peminjaman gagal. Silakan coba lagi.',
        };

        parent::__construct($message.' ['.$errorCode.']');
    }

    public static function duplicate(?string $field = null): self
    {
        return new self('EQ_DUPLICATE', $field);
    }

    public static function unitBorrowed(): self
    {
        return new self('EQ_DUPLICATE', 'items', 'Unit telah dipinjam oleh transaksi lain.');
    }

    public static function concurrency(?string $field = null): self
    {
        return new self('EQ_CONCURRENCY', $field);
    }

    public static function stale(string $field): self
    {
        return new self('EQ_STALE', $field);
    }

    public static function database(): self
    {
        return new self('EQ_DATABASE');
    }

    public static function unexpected(): self
    {
        return new self('EQ_UNEXPECTED');
    }

    public function report(LoggerInterface $logger): void
    {
        // Deliberately omit exception objects, traces, SQL, bindings and input.
        $logger->warning('Equipment operation failed.', ['error_code' => $this->errorCode]);
    }

    public function render(Request $request): JsonResponse|Response
    {
        $status = in_array($this->errorCode, ['EQ_DUPLICATE', 'EQ_CONCURRENCY', 'EQ_STALE'], true) ? 409 : 503;
        $headers = ['Cache-Control' => 'private, no-store'];

        return $request->expectsJson()
            ? new JsonResponse(['message' => $this->getMessage(), 'error_code' => $this->errorCode], $status, $headers)
            : new Response($this->getMessage(), $status, [...$headers, 'Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
