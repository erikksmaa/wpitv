<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Agenda\Form;
use App\Livewire\Admin\Agenda\Index;
use App\Models\Coverage;
use App\Models\Program;
use App\Models\Role;
use App\Models\TVClubGeneration;
use App\Models\TVClubGroup;
use App\Models\User;
use App\Models\WpitvAgenda;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class AgendaManagementTest extends TestCase
{
    use DatabaseTransactions;

    private User $superAdmin;

    private User $adminProduction;

    private User $adminNews;

    protected function setUp(): void
    {
        parent::setUp();
        $this->superAdmin = User::factory()->create(['role_id' => Role::where('name', 'Super Admin')->value('id'), 'status' => true]);
        $this->adminProduction = User::factory()->create(['role_id' => Role::where('name', 'Admin Production')->value('id'), 'status' => true]);
        $this->adminNews = User::factory()->create(['role_id' => Role::where('name', 'Admin News')->value('id'), 'status' => true]);
    }

    public function test_model_constants_cast_scopes_and_relations_work(): void
    {
        $program = Program::create(['name' => 'Program Agenda Test', 'slug' => 'program-agenda-test', 'status' => true]);
        $coverage = $this->coverage('PLP-AGENDA-REL');
        $generation = TVClubGeneration::create(['name' => 'Generasi Agenda', 'year' => 2190, 'status' => true]);
        $group = TVClubGroup::create(['generation_id' => $generation->id, 'program_id' => $program->id, 'name' => 'Tim Agenda', 'status' => true]);
        $agenda = $this->agenda(['program_id' => $program->id, 'coverage_id' => $coverage->id, 'group_id' => $group->id, 'created_by' => $this->superAdmin->id]);

        $this->assertTrue($agenda->agenda_date->isSameDay(today()));
        $this->assertTrue($agenda->program->is($program));
        $this->assertTrue($agenda->coverage->is($coverage));
        $this->assertTrue($agenda->group->is($group));
        $this->assertTrue($agenda->creator->is($this->superAdmin));
        $this->assertTrue($program->agendas->first()->is($agenda));
        $this->assertTrue($coverage->agendas->first()->is($agenda));
        $this->assertTrue($group->agendas->first()->is($agenda));
        $this->assertCount(8, WpitvAgenda::TYPES);
    }

    public function test_authorization_matches_operational_policy_pattern(): void
    {
        $this->actingAs($this->superAdmin)->get(route('admin.agenda.index'))->assertOk()->assertSee('Tambah Agenda');
        $this->actingAs($this->adminProduction)->get(route('admin.agenda.index'))->assertOk()->assertSee('Tambah Agenda');
        $this->actingAs($this->adminNews)->get(route('admin.agenda.index'))->assertOk()->assertDontSee('Tambah Agenda');
        Livewire::actingAs($this->adminNews)->test(Form::class)->assertForbidden();
    }

    public function test_admin_can_create_edit_cancel_and_delete_agenda(): void
    {
        Livewire::actingAs($this->adminProduction)->test(Form::class)
            ->set('title', 'Produksi Agenda CRUD')->set('type', 'program_production')->set('agenda_date', '2026-09-12')
            ->set('start_time', '09:00')->set('end_time', '11:00')->set('location', 'Studio WPiTV')
            ->set('status', 'scheduled')->set('visibility', 'public')->call('save')->assertRedirect(route('admin.agenda.index'));
        $agenda = WpitvAgenda::where('title', 'Produksi Agenda CRUD')->firstOrFail();
        $this->assertSame($this->adminProduction->id, $agenda->created_by);

        Livewire::actingAs($this->superAdmin)->test(Form::class, ['agenda' => $agenda])
            ->set('title', 'Produksi Agenda Diperbarui')->call('save')->assertRedirect(route('admin.agenda.index'));
        Livewire::actingAs($this->superAdmin)->test(Index::class)->call('cancel', $agenda->id);
        $this->assertDatabaseHas('wpitv_agendas', ['id' => $agenda->id, 'title' => 'Produksi Agenda Diperbarui', 'status' => 'cancelled', 'created_by' => $this->adminProduction->id]);
        Livewire::actingAs($this->superAdmin)->test(Index::class)->call('delete', $agenda->id);
        $this->assertDatabaseMissing('wpitv_agendas', ['id' => $agenda->id]);
    }

    public function test_validation_rejects_invalid_values_and_earlier_end_time(): void
    {
        Livewire::actingAs($this->superAdmin)->test(Form::class)
            ->set('title', '')->set('type', 'recurring')->set('agenda_date', 'not-date')->set('start_time', '13:00')->set('end_time', '10:00')
            ->set('status', 'unknown')->set('visibility', 'secret')->set('program_id', 999999999)
            ->call('save')->assertHasErrors(['title', 'type', 'agenda_date', 'end_time', 'status', 'visibility', 'program_id']);
    }

    public function test_optional_relations_and_times_are_accepted(): void
    {
        Livewire::actingAs($this->superAdmin)->test(Form::class)
            ->set('title', 'Rapat Tanpa Relasi')->set('type', 'meeting')->set('agenda_date', '2026-10-01')
            ->set('start_time', '')->set('end_time', '')->set('status', 'draft')->set('visibility', 'internal')->call('save')->assertHasNoErrors();
        $this->assertDatabaseHas('wpitv_agendas', ['title' => 'Rapat Tanpa Relasi', 'program_id' => null, 'coverage_id' => null, 'group_id' => null]);
    }

    public function test_search_and_filters_work_without_loading_unrelated_results(): void
    {
        $this->agenda(['title' => 'Live Wisuda Agenda Unik', 'location' => 'Aula Utama', 'type' => 'live', 'status' => 'scheduled', 'visibility' => 'public']);
        $this->agenda(['title' => 'Rapat Internal Rahasia', 'location' => 'Studio', 'type' => 'meeting', 'status' => 'draft', 'visibility' => 'internal']);
        Livewire::actingAs($this->superAdmin)->test(Index::class)->call('setView', 'list')->set('search', 'Wisuda')->set('typeFilter', 'live')->set('statusFilter', 'scheduled')->set('visibilityFilter', 'public')
            ->assertSee('Live Wisuda Agenda Unik')->assertDontSee('Rapat Internal Rahasia');
    }

    public function test_create_from_coverage_only_prefills_safe_fields_and_does_not_save(): void
    {
        $coverage = $this->coverage('PLP-PREFILL-AGENDA', ['name' => 'Pemohon Privat', 'email' => 'privacy@example.test', 'phone' => '08129999999', 'admin_note' => 'Catatan rahasia']);
        $before = WpitvAgenda::count();
        Livewire::actingAs($this->adminProduction)->test(Form::class, ['coverage' => $coverage])
            ->assertSet('coverage_id', $coverage->id)->assertSet('type', 'coverage')->assertSet('title', 'Peliputan '.$coverage->event_name)
            ->assertSet('agenda_date', $coverage->event_date->format('Y-m-d'))->assertSet('start_time', '09:00')->assertSet('location', $coverage->location)
            ->assertSet('description', '');
        $this->assertSame($before, WpitvAgenda::count());
    }

    private function agenda(array $overrides = []): WpitvAgenda
    {
        return WpitvAgenda::create(array_merge(['title' => 'Agenda Test', 'type' => 'other', 'agenda_date' => today()->toDateString(), 'start_time' => '09:00', 'status' => 'scheduled', 'visibility' => 'public'], $overrides));
    }

    private function coverage(string $code, array $overrides = []): Coverage
    {
        return Coverage::create(array_merge(['submission_code' => $code, 'name' => 'Pemohon Agenda', 'email' => 'agenda@example.test', 'phone' => '081234567890', 'event_name' => 'Seminar Agenda', 'event_date' => '2026-09-15', 'event_time' => '09:00', 'location' => 'Aula Kampus', 'description' => 'Deskripsi pemohon yang tidak disalin', 'status' => 'approved'], $overrides));
    }
}
