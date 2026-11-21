<?php

namespace App\Exceptions;

use RuntimeException;

final class TVClubSocialException extends RuntimeException
{
    public function __construct(public readonly string $errorCode)
    {
        parent::__construct(match ($errorCode) {
            'SOCIAL_STALE' => 'Data telah berubah. Muat ulang sebelum menyimpan.',
            'SOCIAL_DUPLICATE' => 'Data sudah tercatat atau nomor revisi telah dipakai.',
            'SOCIAL_STATE' => 'Aksi tidak tersedia untuk data atau periode ini.',
            'SOCIAL_CONFLICT' => 'Data sedang diproses. Muat ulang dan coba kembali.',
            default => 'Operasi laporan media sosial gagal.',
        });
    }
}
