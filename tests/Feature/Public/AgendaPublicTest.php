<?php

namespace Tests\Feature\Public;

use App\Livewire\Public\Agenda\Index;
use App\Models\Coverage;
use App\Models\Program;
use App\Models\User;
use App\Models\WpitvAgenda;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class AgendaPublicTest extends TestCase
{
    use DatabaseTransactions;

    public function test_malformed_month_is_normalized_without_exception(): void
    {
        Livewire::test(Index::class)
            ->set('month', '2026-99-not-a-month')
            ->call('nextMonth')
            ->assertSet('month', now()->addMonth()->format('Y-m'));
    }

    public function test_only_public_scheduled_today_and_future_agendas_are_visible_in_order(): void
    {
        $today = today()->toDateString();
        $this->agenda('Agenda Hari Ini', ['agenda_date' => $today]);
        $this->agenda('Agenda Masa Depan Kedua', ['agenda_date' => today()->addDays(2)->toDateString(), 'start_time' => '11:00']);
        $this->agenda('Agenda Masa Depan Pertama', ['agenda_date' => today()->addDays(2)->toDateString(), 'start_time' => '08:00']);
        $this->agenda('Agenda Internal Rahasia', ['visibility' => 'internal']);
        $this->agenda('Agenda Draft Rahasia', ['status' => 'draft']);
        $this->agenda('Agenda Batal Rahasia', ['status' => 'cancelled']);
        $this->agenda('Agenda Selesai Rahasia', ['status' => 'completed']);
        $this->agenda('Agenda Masa Lalu', ['agenda_date' => today()->subDay()->toDateString()]);

        $response = $this->get(route('agenda.index'));
        $response->assertOk()->assertSeeInOrder(['Agenda Hari Ini', 'Agenda Masa Depan Pertama', 'Agenda Masa Depan Kedua'])
            ->assertDontSee('Agenda Internal Rahasia')->assertDontSee('Agenda Draft Rahasia')->assertDontSee('Agenda Batal Rahasia')->assertDontSee('Agenda Selesai Rahasia');
        $upcomingHtml = Str::between($response->getContent(), 'id="agenda-terdekat"', 'public-agenda-calendar-section');
        $this->assertStringNotContainsString('Agenda Masa Lalu', $upcomingHtml);
    }

    public function test_peliputan_linked_agenda_never_exposes_applicant_or_internal_data(): void
    {
        $coverage = Coverage::create(['submission_code' => 'PLP-PRIVATE-PUBLIC', 'name' => 'Nama Pemohon Privat', 'phone' => '081277788899', 'event_name' => 'Acara Aman', 'event_date' => today()->addDay(), 'location' => 'Aula Aman', 'description' => 'Deskripsi pemohon privat', 'status' => 'approved', 'admin_note' => 'Catatan admin sangat rahasia']);
        $creator = User::factory()->create(['name' => 'Nama Admin Internal']);
        $this->agenda('Peliputan Acara Aman', ['coverage_id' => $coverage->id, 'created_by' => $creator->id]);

        $this->get(route('agenda.index'))->assertOk()->assertSee('Peliputan Acara Aman')
            ->assertDontSee('PLP-PRIVATE-PUBLIC')->assertDontSee('Nama Pemohon Privat')->assertDontSee('private-agenda@example.test')->assertDontSee('081277788899')->assertDontSee('Catatan admin sangat rahasia')->assertDontSee('Nama Admin Internal');
    }

    public function test_active_program_link_works_and_inactive_or_null_program_renders_safely(): void
    {
        $active = Program::create(['name' => 'Program Publik Agenda', 'slug' => 'program-publik-agenda', 'status' => true]);
        $inactive = Program::create(['name' => 'Program Nonaktif Agenda', 'slug' => 'program-nonaktif-agenda', 'status' => false]);
        $this->agenda('Agenda Program Aktif', ['program_id' => $active->id]);
        $this->agenda('Agenda Program Nonaktif', ['program_id' => $inactive->id]);
        $this->agenda('Agenda Tanpa Program');

        $response = $this->get(route('agenda.index'));
        $response->assertOk()->assertSee('Agenda Program Aktif')->assertSee(route('program.show', $active->slug), false)->assertSee('Agenda Program Nonaktif')->assertSee('Agenda Tanpa Program')->assertDontSee('Program Nonaktif Agenda');
    }

    public function test_homepage_teaser_contains_at_most_three_safe_upcoming_agendas(): void
    {
        foreach (range(1, 4) as $number) {
            $this->agenda('Homepage Agenda '.$number, ['agenda_date' => today()->addDays($number)->toDateString()]);
        }
        $this->agenda('Homepage Internal Hidden', ['visibility' => 'internal']);
        $response = $this->get(route('public.home'));
        $response->assertOk()->assertSee('Agenda Terdekat')->assertSee('Homepage Agenda 1')->assertSee('Homepage Agenda 3')->assertDontSee('Homepage Agenda 4')->assertDontSee('Homepage Internal Hidden');
    }

    private function agenda(string $title, array $overrides = []): WpitvAgenda
    {
        return WpitvAgenda::create(array_merge(['title' => $title, 'type' => 'coverage', 'agenda_date' => today()->toDateString(), 'start_time' => '09:00', 'location' => 'Lokasi Publik', 'status' => 'scheduled', 'visibility' => 'public'], $overrides));
    }
}
