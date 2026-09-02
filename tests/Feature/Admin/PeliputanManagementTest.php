<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Peliputan\Form;
use App\Livewire\Admin\Peliputan\Index;
use App\Models\Coverage;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class PeliputanManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected User $superAdmin;

    protected User $adminProduction;

    protected User $adminNews;

    protected User $inactiveUser;

    protected function setUp(): void
    {
        parent::setUp();

        $superAdminRole = Role::where('name', 'Super Admin')->first() ?? Role::find(1);
        $prodRole = Role::where('name', 'Admin Production')->first() ?? Role::find(2);
        $newsRole = Role::where('name', 'Admin News')->first() ?? Role::find(3);

        $this->superAdmin = User::factory()->create([
            'role_id' => $superAdminRole->id,
            'status' => 1,
        ]);

        $this->adminProduction = User::factory()->create([
            'role_id' => $prodRole->id,
            'status' => 1,
        ]);

        $this->adminNews = User::factory()->create([
            'role_id' => $newsRole->id,
            'status' => 1,
        ]);

        $this->inactiveUser = User::factory()->create([
            'role_id' => $superAdminRole->id,
            'status' => 0,
        ]);
    }

    public function test_guest_cannot_access_peliputan(): void
    {
        $this->get(route('admin.peliputan.index'))->assertRedirect(route('login'));
    }

    public function test_inactive_user_cannot_access_peliputan(): void
    {
        $this->actingAs($this->inactiveUser)->get(route('admin.peliputan.index'))->assertRedirect(route('login'));
    }

    public function test_super_admin_can_view_peliputan_index(): void
    {
        $coverage = Coverage::create([
            'submission_code' => 'PLP-20260817-0001',
            'name' => 'Dr. Budi Utomo',
            'phone' => '08123456789',
            'event_name' => 'Seminar Dies Natalis WPiTV',
            'event_date' => '2026-09-10',
            'event_time' => '09:00',
            'location' => 'Auditorium Utama Lt. 3',
            'description' => 'Permohonan peliputan seminar nasional',
            'status' => Coverage::STATUS_PENDING,
        ]);

        $response = $this->actingAs($this->superAdmin)->get(route('admin.peliputan.index'));
        $response->assertStatus(200);
        $response->assertSee('Peliputan & Liputan Acara');
        $response->assertSee('PLP-20260817-0001');
        $response->assertSee('Seminar Dies Natalis WPiTV');
        $response->assertSee('Tambah Peliputan');
    }

    public function test_admin_production_can_view_peliputan_index(): void
    {
        $response = $this->actingAs($this->adminProduction)->get(route('admin.peliputan.index'));
        $response->assertStatus(200);
        $response->assertSee('Peliputan & Liputan Acara');
        $response->assertSee('Tambah Peliputan');
    }

    public function test_admin_news_cannot_view_peliputan_index(): void
    {
        $this->actingAs($this->adminNews)->get(route('admin.peliputan.index'))->assertForbidden();
    }

    public function test_admin_news_cannot_access_create_or_edit_direct_urls(): void
    {
        $coverage = Coverage::create([
            'submission_code' => 'PLP-20260817-0002',
            'name' => 'Pemohon Test',
            'phone' => '08123456789',
            'event_name' => 'Event Direct Test',
            'event_date' => '2026-09-10',
            'location' => 'Aula 1',
            'description' => 'Deskripsi event',
            'status' => Coverage::STATUS_PENDING,
        ]);

        $this->actingAs($this->adminNews)->get(route('admin.peliputan.create'))->assertStatus(403);
        $this->actingAs($this->adminNews)->get(route('admin.peliputan.edit', $coverage))->assertStatus(403);
    }

    public function test_super_admin_can_create_coverage(): void
    {
        Livewire::actingAs($this->superAdmin)
            ->test(Form::class)
            ->set('name', 'Prof. Ahmad Dahlan')
            ->set('phone', '081399887766')
            ->set('event_name', 'Kongres Kebangsaan 2026')
            ->set('event_date', '2026-10-15')
            ->set('event_time', '08:30')
            ->set('location', 'Gedung Rektorat Lt. 2')
            ->set('description', 'Liputan pembukaan dan sesi pleno 1')
            ->set('status', Coverage::STATUS_APPROVED)
            ->set('admin_note', 'Tugaskan Kru 1 dan Kru 2')
            ->call('save')
            ->assertRedirect(route('admin.peliputan.index'));

        $this->assertDatabaseHas('tr_perliputan', [
            'event_name' => 'Kongres Kebangsaan 2026',
            'name' => 'Prof. Ahmad Dahlan',
            'status' => 'approved',
            'admin_note' => 'Tugaskan Kru 1 dan Kru 2',
        ]);
    }

    public function test_admin_production_can_create_coverage(): void
    {
        Livewire::actingAs($this->adminProduction)
            ->test(Form::class)
            ->set('name', 'Koordinator Kemahasiswaan')
            ->set('phone', '081555443322')
            ->set('event_name', 'Pekan Olahraga Mahasiswa')
            ->set('event_date', '2026-11-01')
            ->set('location', 'GOR Kampus')
            ->set('description', 'Liputan final turnamen futsal')
            ->set('status', Coverage::STATUS_PENDING)
            ->call('save')
            ->assertRedirect(route('admin.peliputan.index'));

        $this->assertDatabaseHas('tr_perliputan', [
            'event_name' => 'Pekan Olahraga Mahasiswa',
            'name' => 'Koordinator Kemahasiswaan',
            'status' => 'pending',
        ]);
    }

    public function test_validation_works(): void
    {
        Livewire::actingAs($this->superAdmin)
            ->test(Form::class)
            ->set('name', '')
            ->set('phone', '')
            ->set('event_name', '')
            ->set('event_date', '')
            ->set('location', '')
            ->set('description', '')
            ->call('save')
            ->assertHasErrors(['name', 'phone', 'event_name', 'event_date', 'location', 'description']);
    }

    public function test_super_admin_can_edit_coverage(): void
    {
        $coverage = Coverage::create([
            'submission_code' => 'PLP-20260817-0003',
            'name' => 'Nama Sebelum Edit',
            'phone' => '0811111111',
            'event_name' => 'Event Sebelum Edit',
            'event_date' => '2026-09-20',
            'location' => 'Ruang Teater',
            'description' => 'Rincian sebelum edit',
            'status' => Coverage::STATUS_PENDING,
        ]);

        Livewire::actingAs($this->superAdmin)
            ->test(Form::class, ['coverage' => $coverage])
            ->set('name', 'Nama Sesudah Edit')
            ->set('event_name', 'Event Sesudah Edit')
            ->set('status', Coverage::STATUS_IN_PROGRESS)
            ->call('save')
            ->assertRedirect(route('admin.peliputan.index'));

        $this->assertDatabaseHas('tr_perliputan', [
            'id' => $coverage->id,
            'name' => 'Nama Sesudah Edit',
            'event_name' => 'Event Sesudah Edit',
            'status' => 'in_progress',
        ]);
    }

    public function test_super_admin_and_admin_production_can_delete_coverage(): void
    {
        $coverage = Coverage::create([
            'submission_code' => 'PLP-20260817-0004',
            'name' => 'Hapus Test',
            'phone' => '0899999999',
            'event_name' => 'Event Mau Dihapus',
            'event_date' => '2026-09-25',
            'location' => 'Studio 2',
            'description' => 'Deskripsi hapus',
            'status' => Coverage::STATUS_PENDING,
        ]);

        Livewire::actingAs($this->adminProduction)
            ->test(Index::class)
            ->call('delete', $coverage->id);

        $this->assertDatabaseMissing('tr_perliputan', ['id' => $coverage->id]);
    }

    public function test_admin_news_cannot_delete_coverage_via_livewire(): void
    {
        $coverage = Coverage::create([
            'submission_code' => 'PLP-20260817-0005',
            'name' => 'Hapus Denied Test',
            'phone' => '0899999998',
            'event_name' => 'Event Denied Delete',
            'event_date' => '2026-09-25',
            'location' => 'Studio 3',
            'description' => 'Deskripsi denied delete',
            'status' => Coverage::STATUS_PENDING,
        ]);

        $this->actingAs($this->adminNews)->get(route('admin.peliputan.index'))->assertForbidden();

        $this->assertDatabaseHas('tr_perliputan', ['id' => $coverage->id]);
    }

    public function test_quick_status_update_works(): void
    {
        $coverage = Coverage::create([
            'submission_code' => 'PLP-20260817-0006',
            'name' => 'Quick Status Test',
            'phone' => '0877777777',
            'event_name' => 'Event Quick Status',
            'event_date' => '2026-09-30',
            'location' => 'Auditorium 2',
            'description' => 'Kebutuhan quick status',
            'status' => Coverage::STATUS_PENDING,
        ]);

        Livewire::actingAs($this->superAdmin)
            ->test(Index::class)
            ->call('openStatusModal', $coverage->id)
            ->set('newStatus', Coverage::STATUS_COMPLETED)
            ->set('adminNote', 'Liputan selesai diunggah ke YouTube')
            ->call('updateStatus');

        $coverage->refresh();
        $this->assertEquals(Coverage::STATUS_COMPLETED, $coverage->status);
        $this->assertEquals('Liputan selesai diunggah ke YouTube', $coverage->admin_note);
    }

    public function test_admin_news_cannot_update_status(): void
    {
        $coverage = Coverage::create([
            'submission_code' => 'PLP-20260817-0007',
            'name' => 'Status Denied Test',
            'phone' => '0877777776',
            'event_name' => 'Event Denied Status',
            'event_date' => '2026-09-30',
            'location' => 'Auditorium 3',
            'description' => 'Kebutuhan denied status',
            'status' => Coverage::STATUS_PENDING,
        ]);

        $this->actingAs($this->adminNews)->get(route('admin.peliputan.index'))->assertForbidden();
        $this->assertSame(Coverage::STATUS_PENDING, $coverage->refresh()->status);
    }

    public function test_search_works(): void
    {
        Coverage::create([
            'submission_code' => 'PLP-20260817-UNIQUE',
            'name' => 'Pemohon Search Unik',
            'phone' => '0812344321',
            'event_name' => 'Konser Musik Orkestra Mahasiswa',
            'event_date' => '2026-10-10',
            'location' => 'Stadion Kampus Terbuka',
            'description' => 'Deskripsi konser musik',
            'status' => Coverage::STATUS_APPROVED,
        ]);

        Livewire::actingAs($this->superAdmin)
            ->test(Index::class)
            ->set('search', 'Orkestra Mahasiswa')
            ->assertSee('Konser Musik Orkestra Mahasiswa')
            ->set('search', 'PencarianKataKunciTidakAda')
            ->assertDontSee('Konser Musik Orkestra Mahasiswa');
    }

    public function test_status_filter_works(): void
    {
        Coverage::create([
            'submission_code' => 'PLP-STAT-1',
            'name' => 'Pemohon Stat 1',
            'phone' => '081111111',
            'event_name' => 'Acara Status Pending',
            'event_date' => '2026-10-10',
            'location' => 'Lokasi 1',
            'description' => 'Desc 1',
            'status' => Coverage::STATUS_PENDING,
        ]);

        Coverage::create([
            'submission_code' => 'PLP-STAT-2',
            'name' => 'Pemohon Stat 2',
            'phone' => '082222222',
            'event_name' => 'Acara Status Completed',
            'event_date' => '2026-10-10',
            'location' => 'Lokasi 2',
            'description' => 'Desc 2',
            'status' => Coverage::STATUS_COMPLETED,
        ]);

        Livewire::actingAs($this->superAdmin)
            ->test(Index::class)
            ->set('statusFilter', Coverage::STATUS_COMPLETED)
            ->assertSee('Acara Status Completed')
            ->assertDontSee('Acara Status Pending');
    }

    public function test_date_filter_works(): void
    {
        Coverage::create([
            'submission_code' => 'PLP-DATE-1',
            'name' => 'Pemohon Date 1',
            'phone' => '081111111',
            'event_name' => 'Acara Bulan Oktober',
            'event_date' => '2026-10-15',
            'location' => 'Lokasi D1',
            'description' => 'Desc D1',
            'status' => Coverage::STATUS_PENDING,
        ]);

        Coverage::create([
            'submission_code' => 'PLP-DATE-2',
            'name' => 'Pemohon Date 2',
            'phone' => '082222222',
            'event_name' => 'Acara Bulan Desember',
            'event_date' => '2026-12-25',
            'location' => 'Lokasi D2',
            'description' => 'Desc D2',
            'status' => Coverage::STATUS_PENDING,
        ]);

        Livewire::actingAs($this->superAdmin)
            ->test(Index::class)
            ->set('dateFrom', '2026-12-01')
            ->set('dateTo', '2026-12-31')
            ->assertSee('Acara Bulan Desember')
            ->assertDontSee('Acara Bulan Oktober');
    }

    public function test_sorting_and_pagination_works(): void
    {
        Livewire::actingAs($this->superAdmin)
            ->test(Index::class)
            ->call('sortBy', 'event_name')
            ->assertSet('sortField', 'event_name')
            ->assertSet('sortDirection', 'asc')
            ->set('perPage', 25)
            ->assertSet('perPage', 25);
    }

    public function test_authorized_admin_can_open_contextual_whatsapp_action_from_detail(): void
    {
        $coverage = Coverage::create([
            'submission_code' => 'PLP-WA-DETAIL',
            'name' => 'Pemohon Detail WhatsApp',
            'phone' => '+62 813-4444-5555',
            'event_name' => 'Agenda Detail WhatsApp',
            'event_date' => '2026-09-30',
            'location' => 'Aula Detail',
            'description' => 'Kebutuhan peliputan.',
            'status' => Coverage::STATUS_PENDING,
            'admin_note' => 'Catatan internal yang tidak boleh terkirim',
        ]);

        $component = Livewire::actingAs($this->superAdmin)
            ->test(Index::class)
            ->call('openDetailModal', $coverage->id)
            ->assertSee('Hubungi Pemohon')
            ->assertSee('https://wa.me/6281344445555?text=', false);

        $this->assertStringNotContainsString(
            rawurlencode('Catatan internal yang tidak boleh terkirim'),
            $component->html()
        );
    }

    public function test_admin_without_module_access_cannot_prepare_whatsapp_actions(): void
    {
        $coverage = Coverage::create([
            'submission_code' => 'PLP-WA-DENIED',
            'name' => 'Pemohon WhatsApp Denied',
            'phone' => '081344445556',
            'event_name' => 'Agenda WhatsApp Denied',
            'event_date' => '2026-09-30',
            'location' => 'Aula Denied',
            'description' => 'Kebutuhan peliputan.',
            'status' => Coverage::STATUS_PENDING,
        ]);

        $this->actingAs($this->adminNews)->get(route('admin.peliputan.index'))->assertForbidden();
    }

    public function test_status_is_persisted_before_whatsapp_draft_becomes_available(): void
    {
        $coverage = Coverage::create([
            'submission_code' => 'PLP-WA-SAVE-FIRST',
            'name' => 'Pemohon Save First',
            'phone' => '081355556666',
            'event_name' => 'Agenda Save First',
            'event_date' => '2026-09-30',
            'location' => 'Aula Save First',
            'description' => 'Kebutuhan peliputan.',
            'status' => Coverage::STATUS_PENDING,
            'admin_note' => 'Catatan rahasia sebelum update',
        ]);

        $component = Livewire::actingAs($this->adminProduction)
            ->test(Index::class)
            ->call('openStatusModal', $coverage->id)
            ->set('newStatus', Coverage::STATUS_IN_PROGRESS)
            ->set('adminNote', 'Catatan rahasia sesudah update')
            ->call('updateStatusAndPrepareWhatsApp')
            ->assertHasNoErrors()
            ->assertSet('preparedWhatsAppCode', 'PLP-WA-SAVE-FIRST');

        $coverage->refresh();
        $url = $component->get('preparedWhatsAppUrl');

        $this->assertSame(Coverage::STATUS_IN_PROGRESS, $coverage->status);
        $this->assertSame('Catatan rahasia sesudah update', $coverage->admin_note);
        $this->assertStringStartsWith('https://wa.me/6281355556666?text=', $url);
        $this->assertStringContainsString(rawurlencode('Sedang Diproses'), $url);
        $this->assertStringNotContainsString(rawurlencode('Catatan rahasia sesudah update'), $url);
    }

    public function test_invalid_client_phone_never_generates_whatsapp_url_but_keeps_saved_status(): void
    {
        $coverage = Coverage::create([
            'submission_code' => 'PLP-WA-BAD-PHONE',
            'name' => 'Pemohon Nomor Invalid',
            'phone' => 'nomor invalid',
            'event_name' => 'Agenda Nomor Invalid',
            'event_date' => '2026-09-30',
            'location' => 'Aula Invalid',
            'description' => 'Kebutuhan peliputan.',
            'status' => Coverage::STATUS_PENDING,
        ]);

        Livewire::actingAs($this->superAdmin)
            ->test(Index::class)
            ->call('openStatusModal', $coverage->id)
            ->set('newStatus', Coverage::STATUS_REJECTED)
            ->call('updateStatusAndPrepareWhatsApp')
            ->assertHasErrors(['whatsapp'])
            ->assertSet('preparedWhatsAppUrl', null);

        $this->assertDatabaseHas('tr_perliputan', [
            'id' => $coverage->id,
            'status' => Coverage::STATUS_REJECTED,
        ]);
    }

    public function test_edit_form_can_save_latest_status_then_prepare_whatsapp_draft(): void
    {
        $coverage = Coverage::create([
            'submission_code' => 'PLP-WA-EDIT-FORM',
            'name' => 'Pemohon Edit Form',
            'phone' => '081366667777',
            'event_name' => 'Agenda Edit Form',
            'event_date' => '2026-09-30',
            'location' => 'Aula Edit',
            'description' => 'Kebutuhan peliputan.',
            'status' => Coverage::STATUS_PENDING,
        ]);

        $component = Livewire::actingAs($this->superAdmin)
            ->test(Form::class, ['coverage' => $coverage])
            ->assertSee('Hubungi Pemohon')
            ->set('status', Coverage::STATUS_COMPLETED)
            ->set('admin_note', 'Catatan internal edit form')
            ->call('saveAndPrepareWhatsApp')
            ->assertHasNoErrors();

        $coverage->refresh();
        $url = $component->get('preparedWhatsAppUrl');

        $this->assertSame(Coverage::STATUS_COMPLETED, $coverage->status);
        $this->assertStringStartsWith('https://wa.me/6281366667777?text=', $url);
        $this->assertStringContainsString(rawurlencode('Selesai'), $url);
        $this->assertStringNotContainsString(rawurlencode('Catatan internal edit form'), $url);
    }
}
