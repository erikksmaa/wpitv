<?php

namespace Tests\Feature\Public;

use App\Livewire\Public\About;
use App\Models\News;
use App\Models\Program;
use App\Models\TVClubGeneration;
use App\Models\TVClubMember;
use App\Models\Video;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class AboutPageTest extends TestCase
{
    use DatabaseTransactions;

    public function test_about_page_is_public_and_uses_the_brand_story(): void
    {
        $this->get(route('public.about'))
            ->assertOk()
            ->assertSee('Membawa cerita kampus lebih dekat melalui layar.')
            ->assertSee('Widya Pratama Internet Televisi')
            ->assertSee('Brings The World Into You')
            ->assertSee('Visi · redaksi profil terdahulu')
            ->assertSee('STMIK Widya Pratama');
    }

    public function test_about_page_has_a_single_primary_heading_and_dynamic_metadata(): void
    {
        $response = $this->get(route('public.about'))->assertOk();

        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
        $response
            ->assertSee('<title>Tentang WPiTV | WPiTV — Televisi Digital Kampus</title>', false)
            ->assertSee('Kenali WPiTV, media televisi digital Institut Widya Pratama', false);
    }

    public function test_about_navigation_points_to_real_public_routes(): void
    {
        $response = $this->get(route('public.about'))->assertOk();

        foreach (['public.home', 'program.index', 'video.index', 'news.index', 'tvclub.index', 'peliputan.create', 'peliputan.status'] as $route) {
            $response->assertSee('href="'.route($route).'"', false);
        }
    }

    public function test_about_statistics_are_exact_database_counts(): void
    {
        Livewire::test(About::class)
            ->assertViewHas('stats', fn (array $stats): bool => $stats === [
                'programs' => Program::active()->count(),
                'videos' => Video::count(),
                'news' => News::count(),
                'members' => TVClubMember::where('status', true)->count(),
            ]);
    }

    public function test_about_page_never_exposes_student_number(): void
    {
        $generation = $this->generation('Generasi Privasi');
        $studentNumber = 'PRIVATE-'.uniqid();

        TVClubMember::create([
            'generation_id' => $generation->id,
            'student_number' => $studentNumber,
            'name' => 'Anggota Aman',
            'status' => true,
        ]);

        $this->get(route('public.about'))
            ->assertOk()
            ->assertSee('Anggota Aman')
            ->assertDontSee($studentNumber);
    }

    public function test_crew_preview_is_limited_to_the_six_latest_active_members(): void
    {
        TVClubMember::query()->update(['status' => false]);
        $generation = $this->generation('Generasi Enam Kru');
        $names = [];

        foreach (range(1, 8) as $index) {
            $names[$index] = "Kru Tentang {$index}";
            TVClubMember::create([
                'generation_id' => $generation->id,
                'student_number' => 'ABOUT-'.$index.'-'.uniqid(),
                'name' => $names[$index],
                'status' => true,
            ]);
        }

        $response = $this->get(route('public.about'))->assertOk();

        foreach (range(3, 8) as $index) {
            $response->assertSee($names[$index]);
        }

        $response->assertDontSee($names[1])->assertDontSee($names[2]);
    }

    public function test_crew_without_a_photo_uses_initials(): void
    {
        TVClubMember::query()->update(['status' => false]);
        $member = TVClubMember::create([
            'generation_id' => $this->generation('Generasi Avatar')->id,
            'student_number' => 'AVATAR-'.uniqid(),
            'name' => 'Nadia Putri',
            'photo' => null,
            'status' => true,
        ]);

        $this->get(route('public.about'))
            ->assertOk()
            ->assertSee($member->name)
            ->assertSee('NP');
    }

    public function test_about_page_has_a_clear_empty_crew_state(): void
    {
        TVClubMember::query()->update(['status' => false]);

        $this->get(route('public.about'))
            ->assertOk()
            ->assertSee('Kru akan segera diperkenalkan')
            ->assertSee('Profil anggota aktif TV Club belum tersedia untuk ditampilkan.');
    }

    public function test_about_copy_does_not_invent_history_or_leadership(): void
    {
        $this->get(route('public.about'))
            ->assertOk()
            ->assertDontSee('didirikan pada')
            ->assertDontSee('tahun berdiri')
            ->assertDontSee('kepala UPT')
            ->assertDontSee('Victor');
    }

    public function test_public_layout_links_to_the_about_route_on_other_pages(): void
    {
        $this->get(route('public.home'))
            ->assertOk()
            ->assertSee('href="'.route('public.about').'"', false);
    }

    private function generation(string $name): TVClubGeneration
    {
        return TVClubGeneration::create([
            'name' => $name.' '.uniqid(),
            'year' => 2099,
            'description' => 'Data pengujian halaman Tentang.',
            'status' => true,
        ]);
    }
}
