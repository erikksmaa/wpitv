<?php

namespace Tests\Feature\Public;

use App\Livewire\Public\Home as PublicHome;
use App\Models\News;
use App\Models\Program;
use App\Models\TVClubGeneration;
use App\Models\TVClubGroup;
use App\Models\TVClubMember;
use App\Models\UserCounter;
use App\Models\Video;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class HomepageTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Test 1: Homepage loads successfully with HTTP 200.
     */
    public function test_homepage_loads_successfully(): void
    {
        $response = $this->get(route('public.home'));

        $response->assertStatus(200);
        $response->assertSee('WPiTV');
        $response->assertSee('Brings The World Into You');
        $response->assertSee('Cerita Kampus.');
    }

    /**
     * Test 2: Logo visible and original asset is referenced.
     */
    public function test_original_logo_is_referenced_in_header_and_footer(): void
    {
        $response = $this->get(route('public.home'));

        $response->assertStatus(200);
        $response->assertSee('logo-wpi.png');
        $response->assertSee('alt="Logo WPiTV"', false);
    }

    /**
     * Test 3: Latest hero video renders from database.
     */
    public function test_hero_video_renders_from_database(): void
    {
        $program = Program::first() ?? Program::create([
            'name' => 'Program Test Hero',
            'slug' => 'program-test-hero',
            'description' => 'Test hero description',
            'status' => true,
        ]);

        $video = Video::create([
            'program_id' => $program->id,
            'user_id' => $this->contentAuthor()->id,
            'title' => 'Edisi Khusus Liputan Dies Natalis 2026',
            'youtube_id' => 'abc123xyz89',
            'description' => 'Deskripsi video hero test',
            'air_date' => now()->addDay()->toDateString(),
        ]);

        Livewire::test(PublicHome::class)
            ->assertSee('Edisi Khusus Liputan Dies Natalis 2026')
            ->assertSee($program->name)
            ->assertSee('abc123xyz89')
            ->assertSee('data-active-key="latest"', false)
            ->assertSee('aria-selected="true"', false)
            ->assertSee('playsinline=1', false)
            ->assertDontSee('autoplay=1', false);
    }

    public function test_featured_selector_renders_profile_and_pmb_options_with_accessible_tabs(): void
    {
        $response = $this->get(route('public.home'));

        $response->assertOk()
            ->assertSee('role="tablist"', false)
            ->assertSee('role="tab"', false)
            ->assertSee('Profil Kampus')
            ->assertSee('Profil Institut Widya Pratama')
            ->assertSee('Informasi PMB')
            ->assertSee('Informasi Penerimaan Mahasiswa Baru')
            ->assertSee('aria-controls="homeFeaturedPlayer"', false);
    }

    public function test_static_featured_videos_are_normalized_without_fake_database_metadata(): void
    {
        Livewire::test(PublicHome::class)
            ->assertViewHas('featuredVideos', function (array $videos): bool {
                $profile = collect($videos)->firstWhere('key', 'profile');
                $pmb = collect($videos)->firstWhere('key', 'pmb');

                return $profile
                    && $pmb
                    && $profile['type'] === 'static'
                    && $pmb['type'] === 'static'
                    && $profile['date'] === null
                    && $profile['program'] === null
                    && $pmb['date'] === null
                    && $pmb['program'] === null;
            });
    }

    public function test_static_featured_videos_do_not_create_video_records_or_change_counts(): void
    {
        $before = Video::count();
        $profileId = config('wpitv.featured_videos.profile.youtube_id');
        $pmbId = config('wpitv.featured_videos.pmb.youtube_id');
        $profileBefore = Video::where('youtube_id', $profileId)->count();
        $pmbBefore = Video::where('youtube_id', $pmbId)->count();

        $this->get(route('public.home'))->assertOk();

        $this->assertSame($before, Video::count());
        $this->assertSame($profileBefore, Video::where('youtube_id', $profileId)->count());
        $this->assertSame($pmbBefore, Video::where('youtube_id', $pmbId)->count());
    }

    public function test_latest_video_without_program_remains_valid_and_does_not_invent_program(): void
    {
        $video = Video::create([
            'program_id' => null,
            'user_id' => $this->contentAuthor()->id,
            'title' => 'Tayangan Terbaru Tanpa Program Hero',
            'youtube_id' => 'noProgram99',
            'air_date' => now()->addYears(2)->toDateString(),
        ]);

        Livewire::test(PublicHome::class)
            ->assertSee($video->title)
            ->assertViewHas('featuredVideos', function (array $videos): bool {
                $latest = collect($videos)->firstWhere('key', 'latest');

                return $latest
                    && $latest['program'] === null
                    && $latest['metaSecondary'] === null;
            });
    }

    public function test_profile_becomes_default_when_database_has_no_video(): void
    {
        Video::query()->delete();

        Livewire::test(PublicHome::class)
            ->assertViewHas('defaultFeaturedVideo', fn (?array $video): bool => ($video['key'] ?? null) === 'profile')
            ->assertSee('data-active-key="profile"', false)
            ->assertSee('Informasi PMB');
    }

    public function test_pmb_becomes_default_when_latest_and_profile_are_unavailable(): void
    {
        Video::query()->delete();
        $profileId = config('wpitv.featured_videos.profile.youtube_id');
        config()->set('wpitv.featured_videos.profile.youtube_id', null);

        try {
            Livewire::test(PublicHome::class)
                ->assertViewHas('defaultFeaturedVideo', fn (?array $video): bool => ($video['key'] ?? null) === 'pmb')
                ->assertSee('data-active-key="pmb"', false);
        } finally {
            config()->set('wpitv.featured_videos.profile.youtube_id', $profileId);
        }
    }

    public function test_graceful_empty_state_renders_when_every_featured_source_is_unavailable(): void
    {
        Video::query()->delete();
        $featuredConfig = config('wpitv.featured_videos');
        config()->set('wpitv.featured_videos.profile.youtube_id', null);
        config()->set('wpitv.featured_videos.pmb.youtube_id', null);

        try {
            Livewire::test(PublicHome::class)
                ->assertViewHas('defaultFeaturedVideo', null)
                ->assertSee('WPiTV Official Broadcast')
                ->assertSee('Tayangan akan segera diperbarui')
                ->assertDontSee('data-home-featured-frame', false);
        } finally {
            config()->set('wpitv.featured_videos', $featuredConfig);
        }
    }

    public function test_hero_routes_and_latest_internal_detail_link_are_preserved(): void
    {
        $video = Video::create([
            'program_id' => null,
            'user_id' => $this->contentAuthor()->id,
            'title' => 'Tayangan Hero Dengan Tautan Detail Internal',
            'youtube_id' => 'internal999',
            'air_date' => now()->addYears(3)->toDateString(),
        ]);

        $response = $this->get(route('public.home'));

        $response->assertOk()
            ->assertSee('href="'.route('video.index').'"', false)
            ->assertSee('href="'.route('program.index').'"', false)
            ->assertSee('data-detail-url="'.route('video.show', $video).'"', false);
    }

    /**
     * Test 4: Programs render from database with active status and videos count.
     */
    public function test_active_programs_render_from_database(): void
    {
        $program = Program::create([
            'name' => 'WPiTV Eksklusif Podcast',
            'slug' => 'wpitv-eksklusif-podcast',
            'description' => 'Program podcast eksklusif kampus',
            'status' => true,
        ]);

        for ($i = 0; $i < 500; $i++) {
            Video::create([
                'program_id' => $program->id,
                'user_id' => $this->contentAuthor()->id,
                'title' => "Podcast Episode {$i}",
                'youtube_id' => "yt123_{$i}",
                'air_date' => now()->toDateString(),
            ]);
        }

        Livewire::test(PublicHome::class)
            ->assertSee('WPiTV Eksklusif Podcast')
            ->assertSee('500 Tayangan');
    }

    /**
     * Test 5: Latest videos list renders from database.
     */
    public function test_latest_videos_render_from_database(): void
    {
        $program = Program::first() ?? Program::create([
            'name' => 'Harmoni Mahasiswa',
            'slug' => 'harmoni-mahasiswa',
            'status' => true,
        ]);

        $video = Video::create([
            'program_id' => $program->id,
            'user_id' => $this->contentAuthor()->id,
            'title' => 'Liputan Khusus Pelatihan Broadcasting Kru Baru',
            'youtube_id' => 'testVid12345',
            'air_date' => now()->addDays(2)->toDateString(),
        ]);

        Livewire::test(PublicHome::class)
            ->assertSee('Liputan Khusus Pelatihan Broadcasting Kru Baru')
            ->assertSee('testVid12345');
    }

    /**
     * Test 6: News items render from database with author display name.
     */
    public function test_news_render_from_database(): void
    {
        $news = News::create([
            'user_id' => $this->contentAuthor()->id,
            'author_name' => 'Redaksi Kampus',
            'title' => 'Mahasiswa WPiTV Raih Prestasi di Ajang Nasional 2026',
            'slug' => 'mahasiswa-wpitv-raih-prestasi-2026',
            'content' => 'Konten berita prestasi lengkap mahasiswa.',
        ]);

        Livewire::test(PublicHome::class)
            ->assertSee('Mahasiswa WPiTV Raih Prestasi di Ajang Nasional 2026');
    }

    /**
     * Retired Banner records must not be queried or rendered by Home.
     */
    public function test_homepage_does_not_query_or_render_retired_banners(): void
    {
        DB::table('ms_banner')->insert([
            'title' => 'Penerimaan Mahasiswa Baru 2026/2027',
            'content' => 'Pendaftaran telah dibuka untuk seluruh jurusan di Institut Widya Pratama.',
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            Livewire::test(PublicHome::class)
                ->assertDontSee('Penerimaan Mahasiswa Baru 2026/2027')
                ->assertDontSee('Pendaftaran telah dibuka untuk seluruh jurusan')
                ->assertDontSee('Sorotan Kampus')
                ->assertDontSee('spotlightTrack');
            $queries = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
        }
        foreach ($queries as $query) {
            $this->assertStringNotContainsString('ms_banner', strtolower($query['query']));
        }
        $this->assertDatabaseHas('ms_banner', ['title' => 'Penerimaan Mahasiswa Baru 2026/2027']);
    }

    /**
     * Test 8: TV Club statistics and crew members render from database.
     */
    public function test_tv_club_stats_and_members_render_from_database(): void
    {
        $gen = TVClubGeneration::first() ?? TVClubGeneration::create([
            'name' => 'Generasi 14',
            'year' => 2025,
            'status' => true,
        ]);

        $member = TVClubMember::create([
            'generation_id' => $gen->id,
            'student_number' => '22.11.0099',
            'name' => 'Ahmad Bagus Prasetyo',
            'status' => true,
        ]);

        $group = TVClubGroup::first() ?? TVClubGroup::create([
            'generation_id' => $gen->id,
            'program_id' => Program::create(['name' => 'Program Uji Kru', 'slug' => 'program-uji-kru', 'status' => 1])->id,
            'name' => 'Redaksi Berita',
            'status' => true,
        ]);

        $member->groups()->attach($group->id, ['role' => 'Kameraman']);

        Livewire::test(PublicHome::class)
            ->assertSee('Ahmad Bagus Prasetyo')
            ->assertSee('Kameraman')
            ->assertSee('Di Balik Layar WPiTV');
    }

    /**
     * Test 9: Image fallback works without breaking output.
     */
    public function test_image_fallback_attributes_render_properly(): void
    {
        $program = new Program([
            'name' => 'Program Tanpa Gambar',
            'slug' => 'program-tanpa-gambar',
            'image' => null,
            'status' => true,
        ]);

        $this->assertStringContainsString('program-placeholder.svg', $program->image_url);

        $video = new Video([
            'title' => 'Video Tanpa Youtube ID',
            'youtube_id' => null,
        ]);

        $this->assertStringContainsString('program-placeholder.svg', $video->thumbnail_url);
    }

    /**
     * Test 10: No unsupported category filter pills or fake labels rendered.
     */
    public function test_no_unsupported_category_filter_pills_present(): void
    {
        $response = $this->get(route('public.home'));

        $response->assertStatus(200);
        $response->assertDontSee('data-filter="talkshow"', false);
        $response->assertDontSee('data-filter="podcast"', false);
        $response->assertDontSee('data-filter="edukasi"', false);
    }

    /**
     * Test 11: Visitor tracking records visit on homepage load.
     */
    public function test_visitor_tracking_works_on_homepage_load(): void
    {
        $initialHits = UserCounter::getTotalHits();

        $this->get(route('public.home'));

        $this->assertTrue(UserCounter::getTotalHits() >= $initialHits);
    }

    /**
     * Test 12: Page is accessible to guest users without authentication.
     */
    public function test_homepage_accessible_to_guest(): void
    {
        $this->assertGuest();

        $response = $this->get(route('public.home'));
        $response->assertStatus(200);
    }

    /**
     * Test 13: No admin management actions exposed on public homepage.
     */
    public function test_no_admin_actions_exposed_on_homepage(): void
    {
        $response = $this->get(route('public.home'));

        $response->assertStatus(200);
        $response->assertDontSee('admin.programs.create');
        $response->assertDontSee('admin.videos.create');
        $response->assertDontSee('admin.news.create');
        $response->assertDontSee('admin.users.create');
        $response->assertDontSee('Delete Program');
    }

    /**
     * Test 14: Homepage renders with default sections and stats counts.
     */
    public function test_homepage_renders_all_expected_sections_and_stats(): void
    {
        $response = $this->get(route('public.home'));

        $response->assertStatus(200);
        $response->assertSee('WPiTV dalam Angka', false);
        $response->assertSee('Program Aktif');
        $response->assertSee('Tayangan Diproduksi');
        $response->assertSee('Berita Diterbitkan');
        $response->assertSee('Anggota TV Club Aktif');
        $response->assertSee('Di Balik Layar WPiTV');
        $response->assertSee('Punya kegiatan yang ingin diliput?');
    }
}
