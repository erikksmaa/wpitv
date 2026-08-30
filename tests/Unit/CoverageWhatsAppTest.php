<?php

namespace Tests\Unit;

use App\Models\Coverage;
use App\Support\CoverageWhatsApp;
use Tests\TestCase;

class CoverageWhatsAppTest extends TestCase
{
    public function test_phone_normalization_handles_common_indonesian_formats(): void
    {
        $service = new CoverageWhatsApp;

        $this->assertSame('6281234567890', $service->normalizePhone('0812 3456-7890'));
        $this->assertSame('6281234567890', $service->normalizePhone('+62 (812) 3456-7890'));
        $this->assertSame('6281234567890', $service->normalizePhone('6281234567890'));
    }

    public function test_phone_normalization_rejects_unsafe_values(): void
    {
        $service = new CoverageWhatsApp;

        $this->assertNull($service->normalizePhone(null));
        $this->assertNull($service->normalizePhone(''));
        $this->assertNull($service->normalizePhone('nomor tidak valid'));
        $this->assertNull($service->normalizePhone('07123'));
    }

    public function test_public_message_contains_context_without_private_or_internal_fields(): void
    {
        $coverage = $this->coverage();
        $message = (new CoverageWhatsApp)->publicToAdminMessage($coverage);

        $this->assertStringContainsString('PLP-20260830-TEST', $message);
        $this->assertStringContainsString('Alya Pemohon', $message);
        $this->assertStringContainsString('Seminar Media Kampus', $message);
        $this->assertStringContainsString('30 Agustus 2026', $message);
        $this->assertStringContainsString('09.00 WIB', $message);
        $this->assertStringContainsString('Aula Institut Widya Pratama', $message);
        $this->assertStringContainsString('Saya ingin menghubungi admin terkait pengajuan peliputan saya.', $message);
        $this->assertStringContainsString('Status Saat Ini: Menunggu Review', $message);
        $this->assertStringContainsString('Mohon informasi atau koordinasi lebih lanjut', $message);
        $this->assertStringContainsString('Menunggu Review', $message);
        $this->assertStringNotContainsString('alya@example.test', $message);
        $this->assertStringNotContainsString('081234567890', $message);
        $this->assertStringNotContainsString('Catatan internal rahasia', $message);
        $this->assertStringNotContainsString('Deskripsi lengkap privat', $message);
    }

    public function test_public_message_omits_optional_time_and_empty_location(): void
    {
        $coverage = $this->coverage(['event_time' => null, 'location' => '']);
        $message = (new CoverageWhatsApp)->publicToAdminMessage($coverage);

        $this->assertStringNotContainsString('Waktu:', $message);
        $this->assertStringNotContainsString('Lokasi:', $message);
    }

    public function test_status_labels_and_messages_cover_existing_workflow(): void
    {
        $service = new CoverageWhatsApp;
        $expectations = [
            Coverage::STATUS_PENDING => ['Menunggu Review', 'proses review'],
            Coverage::STATUS_APPROVED => ['Disetujui', 'telah disetujui'],
            Coverage::STATUS_IN_PROGRESS => ['Sedang Diproses', 'proses koordinasi atau pelaksanaan'],
            Coverage::STATUS_COMPLETED => ['Selesai', 'telah selesai'],
            Coverage::STATUS_REJECTED => ['Ditolak', 'belum dapat kami proses'],
        ];

        foreach ($expectations as $status => [$label, $copy]) {
            $this->assertSame($label, $service->statusLabel($status));
            $this->assertStringContainsString($copy, $service->statusMessage($status));
            $this->assertStringContainsString(
                'Status Saat Ini: '.$label,
                $service->publicToAdminMessage($this->coverage(['status' => $status]))
            );
        }
    }

    public function test_admin_messages_target_client_without_leaking_admin_note(): void
    {
        $coverage = $this->coverage(['status' => Coverage::STATUS_APPROVED]);
        $service = new CoverageWhatsApp;

        $neutral = $service->adminToClientMessage($coverage);
        $update = $service->adminToClientMessage($coverage, true);

        $this->assertStringContainsString('Silakan melanjutkan koordinasi', $neutral);
        $this->assertStringContainsString('Status terbaru:', $update);
        $this->assertStringContainsString('Disetujui', $update);
        $this->assertStringNotContainsString('Catatan internal rahasia', $neutral);
        $this->assertStringNotContainsString('Catatan internal rahasia', $update);
    }

    public function test_urls_use_configured_and_normalized_targets_with_encoded_plain_text(): void
    {
        config()->set('wpitv.whatsapp.admin_number', '0812-0000-1111');
        $coverage = $this->coverage(['phone' => '+62 813-2222-3333']);
        $service = new CoverageWhatsApp;

        $this->assertStringStartsWith('https://wa.me/6281200001111?text=', $service->publicToAdminUrl($coverage));
        $this->assertStringStartsWith('https://wa.me/6281322223333?text=', $service->adminToClientUrl($coverage));
        $this->assertStringContainsString('%0A', $service->publicToAdminUrl($coverage));
    }

    private function coverage(array $overrides = []): Coverage
    {
        return new Coverage(array_merge([
            'submission_code' => 'PLP-20260830-TEST',
            'name' => 'Alya Pemohon',
            'email' => 'alya@example.test',
            'phone' => '081234567890',
            'event_name' => 'Seminar Media Kampus',
            'event_date' => '2026-08-30',
            'event_time' => '09:00',
            'location' => 'Aula Institut Widya Pratama',
            'description' => 'Deskripsi lengkap privat',
            'status' => Coverage::STATUS_PENDING,
            'admin_note' => 'Catatan internal rahasia',
        ], $overrides));
    }
}
