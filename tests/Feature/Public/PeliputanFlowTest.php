<?php

namespace Tests\Feature\Public;

use App\Livewire\Public\Peliputan\Create;
use App\Livewire\Public\Peliputan\Status;
use App\Models\Coverage;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

class PeliputanFlowTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('peliputan-status:127.0.0.1');
        RateLimiter::clear('peliputan-submit:127.0.0.1');
    }

    /**
     * Test 1: Landing page loads successfully.
     */
    public function test_peliputan_landing_loads(): void
    {
        $response = $this->get(route('peliputan.index'));

        $response->assertStatus(200);
        $response->assertSee('Punya kegiatan yang ingin diliput?');
        $response->assertSee('Dari Pengajuan hingga Peliputan');
        $response->assertSee('Kegiatan yang Bisa Diajukan');
        $response->assertSee('Siapkan Informasi Kegiatan');
        $response->assertSee('Cek Status Pengajuan');
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
        $response->assertDontSee('pasti diliput oleh tim WPiTV');
        $response->assertDontSee('24 jam');
        $response->assertDontSee('2 hari kerja');
    }

    /**
     * Test 2: Create form page loads.
     */
    public function test_create_page_loads(): void
    {
        $response = $this->get(route('peliputan.create'));

        $response->assertStatus(200);
        $response->assertSee('Ceritakan Kegiatan Anda');
        $response->assertSee('Tentang Pemohon');
        $response->assertSee('Tentang Kegiatan');
        $response->assertSee('Detail Peliputan');
        $response->assertSee('Review &amp; Kirim', false);
        foreach (['name', 'email', 'phone', 'event_name', 'event_date', 'event_time', 'location', 'description'] as $field) {
            $response->assertSee('wire:model="'.$field.'"', false);
        }
        $response->assertDontSee('wire:model="coverage_type"', false);
        $response->assertDontSee('wire:model="organization"', false);
        $response->assertDontSee('type="file"', false);
    }

    /**
     * Test 3: Guest can submit coverage request.
     */
    public function test_guest_can_submit_coverage(): void
    {
        $this->assertGuest();

        Livewire::test(Create::class)
            ->set('name', 'BEM Institut Widya Pratama')
            ->set('email', 'bem@stmik-wp.ac.id')
            ->set('phone', '081234567890')
            ->set('event_name', 'Masa Pengenalan Kampus 2026')
            ->set('event_date', '2026-09-10')
            ->set('event_time', '08:00')
            ->set('location', 'Auditorium Kampus')
            ->set('description', 'Kebutuhan liputan dokumentasi video dan wawancara panitia.')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertRedirect();
    }

    /**
     * Test 4: Required validation works on empty inputs.
     */
    public function test_required_validation_works(): void
    {
        Livewire::test(Create::class)
            ->set('event_date', '')
            ->call('submit')
            ->assertHasErrors(['name', 'email', 'phone', 'event_name', 'event_date', 'location', 'description']);
    }

    /**
     * Test 5: Max length validation follows schema.
     */
    public function test_max_length_validation_follows_schema(): void
    {
        Livewire::test(Create::class)
            ->set('name', str_repeat('A', 101))
            ->set('email', 'not-an-email')
            ->set('phone', str_repeat('0', 26))
            ->set('event_name', str_repeat('B', 201))
            ->set('location', str_repeat('C', 256))
            ->call('submit')
            ->assertHasErrors(['name', 'email', 'phone', 'event_name', 'location']);
    }

    /**
     * Test 6: Valid submission creates record in tr_perliputan.
     */
    public function test_valid_submission_creates_one_record_in_tr_perliputan(): void
    {
        $uniqueEvent = 'Acara Spesial Fest '.uniqid();

        Livewire::test(Create::class)
            ->set('name', 'Panitia Fest')
            ->set('email', 'fest@stmik-wp.ac.id')
            ->set('phone', '089876543210')
            ->set('event_name', $uniqueEvent)
            ->set('event_date', '2026-10-01')
            ->set('location', 'Gedung Serbaguna')
            ->set('description', 'Dokumentasi lengkap acara fest.')
            ->call('submit');

        $this->assertDatabaseHas('tr_perliputan', [
            'event_name' => $uniqueEvent,
            'name' => 'Panitia Fest',
            'status' => 'pending',
        ]);
    }

    /**
     * Test 7: Correct initial status stored as pending.
     */
    public function test_correct_initial_status_stored_as_pending(): void
    {
        $code = Coverage::generateSubmissionCode();
        $coverage = Coverage::create([
            'submission_code' => $code,
            'name' => 'Pemohon Initial Test',
            'email' => 'initial@test.com',
            'phone' => '081111111111',
            'event_name' => 'Agenda Initial Status',
            'event_date' => '2026-11-01',
            'location' => 'Kampus WPiTV',
            'description' => 'Deskripsi.',
            'status' => Coverage::STATUS_PENDING,
        ]);

        $this->assertEquals(Coverage::STATUS_PENDING, $coverage->status);
    }

    /**
     * Test 8: Success page loads with valid code.
     */
    public function test_success_page_loads_with_valid_code(): void
    {
        $code = Coverage::generateSubmissionCode();
        Coverage::create([
            'submission_code' => $code,
            'name' => 'Pemohon Sukses',
            'email' => 'sukses@test.com',
            'phone' => '082222222222',
            'event_name' => 'Acara Sukses Terkirim',
            'event_date' => '2026-12-01',
            'location' => 'Kampus',
            'description' => 'Deskripsi.',
            'status' => 'pending',
        ]);

        $response = $this->get(route('peliputan.success', $code));

        $response->assertStatus(200);
        $response->assertSee($code);
        $response->assertSee('Acara Sukses Terkirim');
    }

    /**
     * Test 9: Status lookup works with code and phone.
     */
    public function test_status_lookup_works_with_code_and_phone(): void
    {
        $code = Coverage::generateSubmissionCode();
        $coverage = Coverage::create([
            'submission_code' => $code,
            'name' => 'Pemohon Lookup Phone',
            'email' => 'phone@lookup.com',
            'phone' => '081299887766',
            'event_name' => 'Acara Lookup Phone',
            'event_date' => '2026-08-30',
            'location' => 'Ruang 301',
            'description' => 'Deskripsi acara.',
            'status' => 'pending',
        ]);

        Livewire::test(Status::class)
            ->set('submission_code', $code)
            ->set('contact', '081299887766')
            ->call('check')
            ->assertHasNoErrors()
            ->assertSee($coverage->event_name);
    }

    /**
     * Test 10: Status lookup works with code and email.
     */
    public function test_status_lookup_works_with_code_and_email(): void
    {
        $code = Coverage::generateSubmissionCode();
        $coverage = Coverage::create([
            'submission_code' => $code,
            'name' => 'Pemohon Lookup Email',
            'email' => 'pemohon_email@domain.com',
            'phone' => '081200000000',
            'event_name' => 'Acara Lookup Email',
            'event_date' => '2026-08-30',
            'location' => 'Ruang 302',
            'description' => 'Deskripsi acara.',
            'status' => 'approved',
        ]);

        Livewire::test(Status::class)
            ->set('submission_code', $code)
            ->set('contact', 'pemohon_email@domain.com')
            ->call('check')
            ->assertHasNoErrors()
            ->assertSee($coverage->event_name)
            ->assertSee('Disetujui');
    }

    /**
     * Test 11: Invalid lookup returns error message.
     */
    public function test_invalid_lookup_returns_error_message(): void
    {
        Livewire::test(Status::class)
            ->set('submission_code', 'PLP-FAKE-9999')
            ->set('contact', '081234567890')
            ->call('check')
            ->assertHasErrors(['lookup']);
    }

    /**
     * Test 12: Wrong verification contact fails.
     */
    public function test_wrong_verification_contact_fails(): void
    {
        $code = Coverage::generateSubmissionCode();
        Coverage::create([
            'submission_code' => $code,
            'name' => 'Pemohon Salah Kontak',
            'email' => 'benar@test.com',
            'phone' => '081211112222',
            'event_name' => 'Acara Salah Kontak',
            'event_date' => '2026-09-01',
            'location' => 'Kampus',
            'description' => 'Deskripsi.',
            'status' => 'pending',
        ]);

        Livewire::test(Status::class)
            ->set('submission_code', $code)
            ->set('contact', '089999999999')
            ->call('check')
            ->assertHasErrors(['lookup']);
    }

    public function test_status_lookup_normalizes_full_phone_but_rejects_short_suffix(): void
    {
        $coverage = Coverage::create([
            'submission_code' => 'PLP-LOOKUP-NORMALIZED',
            'name' => 'Pemohon Normalisasi',
            'email' => 'normalisasi@example.test',
            'phone' => '0812-1111-2222',
            'event_name' => 'Acara Normalisasi Kontak',
            'event_date' => '2026-09-01',
            'location' => 'Kampus',
            'description' => 'Deskripsi.',
            'status' => Coverage::STATUS_PENDING,
        ]);

        Livewire::test(Status::class)
            ->set('submission_code', $coverage->submission_code)
            ->set('contact', '+62 812 1111 2222')
            ->call('check')
            ->assertHasNoErrors()
            ->assertSee('Acara Normalisasi Kontak');

        Livewire::test(Status::class)
            ->set('submission_code', $coverage->submission_code)
            ->set('contact', '2222')
            ->call('check')
            ->assertHasErrors(['lookup'])
            ->assertDontSee('Acara Normalisasi Kontak');
    }

    /**
     * Test 13: Private contact is masked on status page.
     */
    public function test_private_contact_is_masked(): void
    {
        $code = Coverage::generateSubmissionCode();
        Coverage::create([
            'submission_code' => $code,
            'name' => 'Pemohon Masking',
            'email' => 'privat@test.com',
            'phone' => '081234567899',
            'event_name' => 'Acara Masking Test',
            'event_date' => '2026-09-01',
            'location' => 'Kampus',
            'description' => 'Deskripsi.',
            'status' => 'pending',
        ]);

        $component = new Status;
        $maskedPhone = $component->maskPhone('081234567899');
        $this->assertEquals('0812******99', $maskedPhone);

        $maskedEmail = $component->maskEmail('privat@test.com');
        $this->assertEquals('p*****@test.com', $maskedEmail);
    }

    /**
     * Test 14: Internal admin data not exposed on status page.
     */
    public function test_internal_admin_data_not_exposed(): void
    {
        $response = $this->get(route('peliputan.status'));

        $response->assertStatus(200);
        $response->assertDontSee('admin_users');
        $response->assertDontSee('password');
    }

    /**
     * Test 15: Admin can see public-created submission in tr_perliputan.
     */
    public function test_admin_can_see_public_created_submission(): void
    {
        $code = Coverage::generateSubmissionCode();
        $coverage = Coverage::create([
            'submission_code' => $code,
            'name' => 'Pemohon Untuk Admin',
            'email' => 'admin_cek@test.com',
            'phone' => '085555555555',
            'event_name' => 'Acara Untuk Verifikasi Admin',
            'event_date' => '2026-10-15',
            'location' => 'Gedung Utama',
            'description' => 'Liputan admin.',
            'status' => 'pending',
        ]);

        $found = Coverage::where('submission_code', $code)->first();
        $this->assertNotNull($found);
        $this->assertEquals('Acara Untuk Verifikasi Admin', $found->event_name);
    }

    /**
     * Test 16: Admin status update reflected publicly.
     */
    public function test_admin_status_update_reflected_publicly(): void
    {
        $code = Coverage::generateSubmissionCode();
        $coverage = Coverage::create([
            'submission_code' => $code,
            'name' => 'Pemohon Update Status',
            'email' => 'update@test.com',
            'phone' => '087777777777',
            'event_name' => 'Acara Update Status',
            'event_date' => '2026-10-20',
            'location' => 'Aula',
            'description' => 'Deskripsi.',
            'status' => 'pending',
        ]);

        $coverage->update(['status' => 'completed', 'admin_note' => 'Dokumentasi telah selesai diunggah.']);

        Livewire::test(Status::class)
            ->set('submission_code', $code)
            ->set('contact', '087777777777')
            ->call('check')
            ->assertHasNoErrors()
            ->assertSee('Selesai')
            ->assertDontSee('Dokumentasi telah selesai diunggah.');
    }

    /**
     * Test 17: Homepage links use internal peliputan routes.
     */
    public function test_homepage_links_use_internal_peliputan_routes(): void
    {
        $response = $this->get(route('public.home'));

        $response->assertStatus(200);
        $response->assertSee(route('peliputan.create'));
        $response->assertSee(route('peliputan.status'));
    }

    /**
     * Test 18: Layout navigation uses peliputan route.
     */
    public function test_layout_navigation_uses_peliputan_route(): void
    {
        $response = $this->get(route('public.home'));

        $response->assertStatus(200);
        $response->assertSee(route('peliputan.index'));
    }

    /**
     * Test 19: Missing success code returns 404.
     */
    public function test_missing_success_code_returns_404(): void
    {
        $response = $this->get(route('peliputan.success', 'PLP-NONEXISTENT-9999'));
        $response->assertStatus(404);
    }

    public function test_success_page_shows_configured_whatsapp_action_without_exposing_internal_data(): void
    {
        config()->set('wpitv.whatsapp.admin_number', '0812-0000-1111');
        $coverage = Coverage::create([
            'submission_code' => 'PLP-WA-SUCCESS',
            'name' => 'Pemohon WhatsApp Sukses',
            'email' => 'private-success@example.test',
            'phone' => '081399999999',
            'event_name' => 'Acara WhatsApp Sukses',
            'event_date' => '2026-08-30',
            'location' => 'Aula Kampus',
            'description' => 'Deskripsi privat sukses',
            'status' => Coverage::STATUS_PENDING,
            'admin_note' => 'Catatan internal sukses',
        ]);

        $this->get(route('peliputan.success', $coverage->submission_code))
            ->assertOk()
            ->assertSee('Hubungi Admin')
            ->assertSee('https://wa.me/6281200001111?text=', false)
            ->assertSee('Status%20Saat%20Ini%3A%20Menunggu%20Review', false)
            ->assertDontSee('private-success@example.test')
            ->assertDontSee('081399999999')
            ->assertDontSee('Deskripsi privat sukses')
            ->assertDontSee('Catatan internal sukses');
    }

    public function test_success_page_keeps_core_flow_when_admin_whatsapp_is_unavailable(): void
    {
        config()->set('wpitv.whatsapp.admin_number', 'invalid');
        $coverage = Coverage::create([
            'submission_code' => 'PLP-WA-NO-ADMIN',
            'name' => 'Pemohon Tanpa WhatsApp',
            'email' => 'no-wa@example.test',
            'phone' => '081399999998',
            'event_name' => 'Acara Tanpa WhatsApp',
            'event_date' => '2026-08-30',
            'location' => 'Aula Kampus',
            'description' => 'Deskripsi.',
            'status' => Coverage::STATUS_PENDING,
        ]);

        $this->get(route('peliputan.success', $coverage->submission_code))
            ->assertOk()
            ->assertSee('Cek Status')
            ->assertDontSee('Hubungi Admin WPiTV melalui WhatsApp')
            ->assertDontSee('https://wa.me/?text=', false);
    }

    public function test_valid_status_lookup_shows_whatsapp_action_but_invalid_lookup_does_not(): void
    {
        config()->set('wpitv.whatsapp.admin_number', '+62 812 0000 1111');
        $coverage = Coverage::create([
            'submission_code' => 'PLP-WA-LOOKUP',
            'name' => 'Pemohon WhatsApp Lookup',
            'email' => 'wa-lookup@example.test',
            'phone' => '081277778888',
            'event_name' => 'Acara WhatsApp Lookup',
            'event_date' => '2026-08-30',
            'location' => 'Studio WPiTV',
            'description' => 'Deskripsi.',
            'status' => Coverage::STATUS_APPROVED,
        ]);

        Livewire::test(Status::class)
            ->set('submission_code', $coverage->submission_code)
            ->set('contact', $coverage->phone)
            ->call('check')
            ->assertSee('Hubungi Admin')
            ->assertSee('https://wa.me/6281200001111?text=', false)
            ->assertSee('Status%20Saat%20Ini%3A%20Disetujui', false);

        Livewire::test(Status::class)
            ->set('submission_code', 'PLP-WA-INVALID')
            ->set('contact', '081200000000')
            ->call('check')
            ->assertHasErrors(['lookup'])
            ->assertDontSee('Hubungi Admin WPiTV melalui WhatsApp')
            ->assertDontSee($coverage->submission_code)
            ->assertDontSee($coverage->event_name);
    }

    public function test_verified_status_whatsapp_action_uses_latest_persisted_status(): void
    {
        config()->set('wpitv.whatsapp.admin_number', '0812-0000-1111');
        $coverage = Coverage::create([
            'submission_code' => 'PLP-WA-LATEST-STATUS',
            'name' => 'Pemohon Status Terbaru',
            'email' => 'latest-status@example.test',
            'phone' => '081277770001',
            'event_name' => 'Agenda Status Terbaru',
            'event_date' => '2026-09-02',
            'event_time' => '10:30',
            'location' => 'Studio WPiTV',
            'description' => 'Deskripsi privat.',
            'status' => Coverage::STATUS_PENDING,
            'admin_note' => 'Catatan internal tidak boleh bocor.',
        ]);

        $coverage->update(['status' => Coverage::STATUS_APPROVED]);

        Livewire::test(Status::class)
            ->set('submission_code', $coverage->submission_code)
            ->set('contact', $coverage->email)
            ->call('check')
            ->assertHasNoErrors()
            ->assertSee('Disetujui')
            ->assertSee('Hubungi Admin')
            ->assertSee('Status%20Saat%20Ini%3A%20Disetujui', false)
            ->assertDontSee('Status%20Saat%20Ini%3A%20Menunggu%20Review', false)
            ->assertDontSee('latest-status@example.test')
            ->assertDontSee('081277770001')
            ->assertDontSee('Catatan internal tidak boleh bocor.');
    }

    public function test_verified_status_hides_whatsapp_action_when_admin_number_is_invalid(): void
    {
        config()->set('wpitv.whatsapp.admin_number', 'invalid');
        $coverage = Coverage::create([
            'submission_code' => 'PLP-WA-STATUS-NO-ADMIN',
            'name' => 'Pemohon Tanpa Nomor Admin',
            'email' => 'no-admin-status@example.test',
            'phone' => '081277770002',
            'event_name' => 'Agenda Tanpa Nomor Admin',
            'event_date' => '2026-09-03',
            'location' => 'Aula Kampus',
            'description' => 'Deskripsi.',
            'status' => Coverage::STATUS_IN_PROGRESS,
        ]);

        Livewire::test(Status::class)
            ->set('submission_code', $coverage->submission_code)
            ->set('contact', $coverage->phone)
            ->call('check')
            ->assertHasNoErrors()
            ->assertSee('Sedang Diproses')
            ->assertDontSee('Hubungi Admin WPiTV melalui WhatsApp')
            ->assertDontSee('https://wa.me/?text=', false);
    }

    public function test_public_submission_rate_limit_remains_enforced(): void
    {
        $key = 'peliputan-submit:127.0.0.1';
        RateLimiter::clear($key);

        try {
            for ($attempt = 0; $attempt < 5; $attempt++) {
                RateLimiter::hit($key, 60);
            }

            Livewire::test(Create::class)
                ->set('name', 'Pemohon Terbatas')
                ->set('email', 'limited@example.test')
                ->set('phone', '081200000001')
                ->set('event_name', 'Agenda Tidak Boleh Tersimpan')
                ->set('event_date', '2026-09-10')
                ->set('location', 'Kampus')
                ->set('description', 'Pengajuan melewati batas.')
                ->call('submit')
                ->assertHasErrors(['general']);

            $this->assertDatabaseMissing('tr_perliputan', ['event_name' => 'Agenda Tidak Boleh Tersimpan']);
        } finally {
            RateLimiter::clear($key);
        }
    }

    public function test_secure_status_lookup_rate_limit_remains_enforced(): void
    {
        $key = 'peliputan-status:127.0.0.1';
        RateLimiter::clear($key);

        try {
            for ($attempt = 0; $attempt < 10; $attempt++) {
                RateLimiter::hit($key, 60);
            }

            Livewire::test(Status::class)
                ->set('submission_code', 'PLP-RATE-LIMIT')
                ->set('contact', '081200000002')
                ->call('check')
                ->assertHasErrors(['lookup'])
                ->assertDontSee('Agenda');
        } finally {
            RateLimiter::clear($key);
        }
    }
}
