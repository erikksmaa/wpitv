<?php

namespace Tests\Feature\Public;

use App\Livewire\Public\GlobalSearch;
use App\Models\Coverage;
use App\Models\News;
use App\Models\Program;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    use DatabaseTransactions;

    public function test_public_layout_mounts_accessible_global_search(): void
    {
        $this->get(route('public.home'))
            ->assertOk()
            ->assertSee('id="searchBtn"', false)
            ->assertSee('aria-controls="globalPublicSearch"', false)
            ->assertSee('id="globalPublicSearch"', false)
            ->assertSee('Cari program, tayangan, atau berita...');
    }

    public function test_global_search_returns_active_program_and_hides_inactive_program(): void
    {
        $active = Program::create(['name' => 'AKTUAL SEARCH PUBLIK', 'slug' => 'aktual-search-publik', 'status' => true]);
        Program::create(['name' => 'AKTUAL SEARCH TERSEMBUNYI', 'slug' => 'aktual-search-tersembunyi', 'status' => false]);

        Livewire::test(GlobalSearch::class)
            ->set('query', 'AKTUAL SEARCH')
            ->assertSee($active->name)
            ->assertSee(route('program.show', $active->slug), false)
            ->assertDontSee('AKTUAL SEARCH TERSEMBUNYI');
    }

    public function test_global_search_returns_video_title_with_public_detail_link(): void
    {
        $video = Video::create(['title' => 'Tayangan Unik Pencarian Global 8742', 'youtube_id' => 'global8742', 'air_date' => today()]);

        Livewire::test(GlobalSearch::class)
            ->set('query', 'Pencarian Global 8742')
            ->assertSee($video->title)
            ->assertSee(route('video.show', $video), false);
    }

    public function test_global_search_returns_news_title_with_public_detail_link(): void
    {
        $news = News::create(['title' => 'Berita Unik Pencarian Global 9631', 'slug' => 'berita-unik-global-9631', 'content' => 'Konten publik.']);

        Livewire::test(GlobalSearch::class)
            ->set('query', 'Pencarian Global 9631')
            ->assertSee($news->title)
            ->assertSee(route('news.show', $news->slug), false);
    }

    public function test_global_search_handles_short_empty_and_missing_queries_without_dumping_results(): void
    {
        Program::create(['name' => 'Program Tidak Boleh Dump 4491', 'slug' => 'program-tidak-boleh-dump-4491', 'status' => true]);

        Livewire::test(GlobalSearch::class)
            ->assertSee('Ketik minimal 2 karakter')
            ->assertDontSee('Program Tidak Boleh Dump 4491')
            ->set('query', 'x')
            ->assertSee('Ketik minimal 2 karakter')
            ->set('query', 'TidakAdaHasilSamaSekali99881')
            ->assertSee('Tidak ada hasil yang ditemukan.');
    }

    public function test_global_search_never_exposes_peliputan_or_user_data(): void
    {
        $privateToken = 'PRIVAT-GLOBAL-'.uniqid();
        Coverage::create([
            'submission_code' => 'PLP-PRIVATE-GLOBAL',
            'name' => $privateToken,
            'phone' => '081299991111',
            'event_name' => 'Agenda Privat Global',
            'event_date' => now()->addWeek()->toDateString(),
            'location' => 'Lokasi privat',
            'description' => 'Tidak boleh dicari.',
            'status' => Coverage::STATUS_PENDING,
        ]);
        User::factory()->create(['username' => strtolower($privateToken), 'name' => $privateToken]);

        Livewire::test(GlobalSearch::class)
            ->set('query', $privateToken)
            ->assertSee('Tidak ada hasil yang ditemukan.')
            ->assertDontSee('Agenda Privat Global')
            ->assertDontSee('private-global@example.test');
    }

    public function test_each_global_search_group_is_limited_to_five_results(): void
    {
        foreach (range(1, 7) as $index) {
            Program::create([
                'name' => "Program Limit Global {$index}",
                'slug' => "program-limit-global-{$index}",
                'status' => true,
            ]);
        }

        $html = Livewire::test(GlobalSearch::class)
            ->set('query', 'Program Limit Global')
            ->html();

        $this->assertSame(5, substr_count($html, 'Program WPiTV'));
    }

    public function test_oversized_global_search_is_bounded(): void
    {
        Livewire::test(GlobalSearch::class)
            ->set('query', str_repeat('a', 500))
            ->assertSet('query', str_repeat('a', 150));
    }
}
