<?php

namespace App\Support;

use App\Models\Coverage;
use App\Services\WebsiteSettings;

class CoverageWhatsApp
{
    public function normalizePhone(?string $phone): ?string
    {
        $normalized = preg_replace('/[\s\-()]/', '', trim((string) $phone));
        $normalized = ltrim((string) $normalized, '+');

        if (str_starts_with($normalized, '08')) {
            $normalized = '62'.substr($normalized, 1);
        }

        if (! preg_match('/^[1-9][0-9]{7,14}$/', $normalized)) {
            return null;
        }

        return $normalized;
    }

    public function adminNumber(): ?string
    {
        return app(WebsiteSettings::class)->coverageWhatsAppNumber();
    }

    public function publicToAdminMessage(Coverage $coverage): string
    {
        $lines = [
            'Halo Admin WPiTV,',
            '',
            'Saya ingin menghubungi admin terkait pengajuan peliputan saya.',
            '',
            'Kode Pengajuan: '.$coverage->submission_code,
            'Kegiatan: '.$coverage->event_name,
            'Tanggal: '.$this->formattedDate($coverage),
        ];

        $lines = array_merge($lines, [
            '',
            'Mohon informasi atau koordinasi lebih lanjut terkait pengajuan tersebut.',
            '',
            'Terima kasih.',
        ]);

        return implode("\n", $lines);
    }

    public function adminToClientMessage(Coverage $coverage, bool $statusUpdate = false): string
    {
        if ($statusUpdate) {
            return implode("\n", [
                'Halo '.$coverage->name.',',
                '',
                'Kami dari WPiTV ingin memberikan update terkait pengajuan peliputan Anda.',
                '',
                'Kode Pengajuan: '.$coverage->submission_code,
                'Kegiatan: '.$coverage->event_name,
                'Tanggal: '.$this->formattedDate($coverage),
                '',
                'Status terbaru:',
                $this->statusLabel($coverage->status),
                '',
                $this->statusMessage($coverage->status),
                '',
                'Silakan menghubungi kami melalui WhatsApp ini jika diperlukan koordinasi lebih lanjut.',
                '',
                'Terima kasih,',
                'WPiTV',
                'Institut Widya Pratama',
            ]);
        }

        return implode("\n", [
            'Halo '.$coverage->name.',',
            '',
            'Kami dari WPiTV menghubungi Anda terkait pengajuan peliputan berikut:',
            '',
            'Kode Pengajuan: '.$coverage->submission_code,
            'Kegiatan: '.$coverage->event_name,
            'Tanggal: '.$this->formattedDate($coverage),
            '',
            'Silakan melanjutkan koordinasi melalui WhatsApp ini.',
            '',
            'Terima kasih,',
            'WPiTV',
            'Institut Widya Pratama',
        ]);
    }

    public function publicToAdminUrl(Coverage $coverage): ?string
    {
        return $this->url($this->adminNumber(), $this->publicToAdminMessage($coverage));
    }

    public function adminToClientUrl(Coverage $coverage, bool $statusUpdate = false): ?string
    {
        return $this->url($this->normalizePhone($coverage->phone), $this->adminToClientMessage($coverage, $statusUpdate));
    }

    public function statusLabel(?string $status): string
    {
        return match ($status) {
            Coverage::STATUS_PENDING => 'Menunggu Review',
            Coverage::STATUS_APPROVED => 'Disetujui',
            Coverage::STATUS_IN_PROGRESS => 'Sedang Diproses',
            Coverage::STATUS_COMPLETED => 'Selesai',
            Coverage::STATUS_REJECTED => 'Ditolak',
            default => 'Menunggu Review',
        };
    }

    public function statusMessage(?string $status): string
    {
        return match ($status) {
            Coverage::STATUS_APPROVED => 'Pengajuan peliputan Anda telah disetujui.',
            Coverage::STATUS_IN_PROGRESS => 'Pengajuan Anda sedang dalam proses koordinasi atau pelaksanaan.',
            Coverage::STATUS_COMPLETED => 'Proses peliputan untuk kegiatan Anda telah selesai.',
            Coverage::STATUS_REJECTED => 'Pengajuan peliputan Anda belum dapat kami proses.',
            default => 'Pengajuan Anda telah kami terima dan sedang dalam proses review.',
        };
    }

    private function formattedDate(Coverage $coverage): string
    {
        return $coverage->event_date?->locale('id')->translatedFormat('j F Y') ?? 'Tanggal belum ditentukan';
    }

    private function url(?string $number, string $message): ?string
    {
        if (! $number) {
            return null;
        }

        return 'https://wa.me/'.$number.'?text='.rawurlencode($message);
    }
}
