<?php

namespace Tests\Feature\Public;

use App\Livewire\Public\Videos\Index as VideosIndex;
use App\Livewire\Public\Videos\Show as VideosShow;
use App\Models\Program;
use App\Models\Video;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class VideoPagesTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Test 1: Video listing loads successfully with HTTP 200.
     */
    public function test_video_listing_loads_successfully(): void
    {
        $this->assertGuest();

        $response = $this->get(route('video.index'));

        $response->assertStatus(200);
        $response->assertSee('Tayangan WPiTV');
        $response->assertSee('Jelajahi tayangan, program, berita video, dan produksi audiovisual WPiTV.');
    }

    /**
     * Test 2: Videos render from database on index page.
     */
    public function test_videos_render_from_database(): void
    {
        $video = Video::create([
            'program_id' => null,
            'user_id' => $this->contentAuthor()->id,
            'title' => 'Video Eksklusif Testing Render Database 2026',
            'youtube_id' => 'renderTest123',
            'air_date' => now()->toDateString(),
        ]);

        Livewire::test(VideosIndex::class)
            ->assertSee('Video Eksklusif Testing Render Database 2026');
    }

    /**
     * Test 3: Search by title works.
     */
    public function test_search_title_works(): void
    {
        $videoA = Video::create([
            'program_id' => null,
            'user_id' => $this->contentAuthor()->id,
            'title' => 'Pelatihan Jurnalistik Kampus Pekalongan',
            'youtube_id' => 'vidA123',
            'air_date' => now()->toDateString(),
        ]);

        $videoB = Video::create([
            'program_id' => null,
            'user_id' => $this->contentAuthor()->id,
            'title' => 'Dokumenter Sejarah Batik Indonesia',
            'youtube_id' => 'vidB123',
            'air_date' => now()->toDateString(),
        ]);

        Livewire::test(VideosIndex::class)
            ->set('search', 'Pelatihan Jurnalistik')
            ->assertSee('Pelatihan Jurnalistik Kampus Pekalongan')
            ->assertDontSee('Dokumenter Sejarah Batik Indonesia');
    }

    /**
     * Test 4: Search by YouTube ID works.
     */
    public function test_search_youtube_id_works(): void
    {
        $video = Video::create([
            'program_id' => null,
            'user_id' => $this->contentAuthor()->id,
            'title' => 'Video Dengan ID Unik Tertentu',
            'youtube_id' => 'xX_uniqueYtId99_Xx',
            'air_date' => now()->toDateString(),
        ]);

        Livewire::test(VideosIndex::class)
            ->set('search', 'xX_uniqueYtId99_Xx')
            ->assertSee('Video Dengan ID Unik Tertentu');
    }

    /**
     * Test 5: Program filter works.
     */
    public function test_program_filter_works(): void
    {
        $progA = Program::create(['name' => 'Program Filter A', 'slug' => 'program-filter-a', 'status' => true]);
        $progB = Program::create(['name' => 'Program Filter B', 'slug' => 'program-filter-b', 'status' => true]);

        $vidA = Video::create([
            'program_id' => $progA->id,
            'user_id' => $this->contentAuthor()->id,
            'title' => 'Video Khusus Program A',
            'youtube_id' => 'progA_vid',
            'air_date' => now()->toDateString(),
        ]);

        $vidB = Video::create([
            'program_id' => $progB->id,
            'user_id' => $this->contentAuthor()->id,
            'title' => 'Video Khusus Program B',
            'youtube_id' => 'progB_vid',
            'air_date' => now()->toDateString(),
        ]);

        Livewire::test(VideosIndex::class)
            ->set('program', (string) $progA->id)
            ->assertSee('Video Khusus Program A')
            ->assertDontSee('Video Khusus Program B');
    }

    /**
     * Test 6: Filter without program works for NULL program_id.
     */
    public function test_without_program_filter_works(): void
    {
        $prog = Program::create(['name' => 'Program Memiliki ID', 'slug' => 'program-memiliki-id', 'status' => true]);

        $vidWithProg = Video::create([
            'program_id' => $prog->id,
            'user_id' => $this->contentAuthor()->id,
            'title' => 'Tayangan Memiliki Program ID',
            'youtube_id' => 'hasProg123',
            'air_date' => now()->toDateString(),
        ]);

        $vidWithoutProg = Video::create([
            'program_id' => null,
            'user_id' => $this->contentAuthor()->id,
            'title' => 'Tayangan Murni Non Program',
            'youtube_id' => 'noProg123',
            'air_date' => now()->toDateString(),
        ]);

        Livewire::test(VideosIndex::class)
            ->set('program', 'without_program')
            ->assertSee('Tayangan Murni Non Program')
            ->assertDontSee('Tayangan Memiliki Program ID');
    }

    /**
     * Test 7: Year filter works.
     */
    public function test_year_filter_works(): void
    {
        $vid2020 = Video::create([
            'program_id' => null,
            'user_id' => $this->contentAuthor()->id,
            'title' => 'Arsip Tayangan Tahun 2020',
            'youtube_id' => 'yr2020',
            'air_date' => '2020-05-15',
        ]);

        $vid2026 = Video::create([
            'program_id' => null,
            'user_id' => $this->contentAuthor()->id,
            'title' => 'Tayangan Terkini Tahun 2026',
            'youtube_id' => 'yr2026',
            'air_date' => '2026-02-10',
        ]);

        Livewire::test(VideosIndex::class)
            ->set('year', '2020')
            ->assertSee('Arsip Tayangan Tahun 2020')
            ->assertDontSee('Tayangan Terkini Tahun 2026');
    }

    /**
     * Test 8: Newest air_date sorting works.
     */
    public function test_newest_sorting_works(): void
    {
        Video::create(['program_id' => null, 'user_id' => $this->contentAuthor()->id, 'title' => 'Tayangan Jadul 2019 Unik Sort', 'youtube_id' => 'j2019', 'air_date' => '2019-01-01']);
        Video::create(['program_id' => null, 'user_id' => $this->contentAuthor()->id, 'title' => 'Tayangan Anyar 2026 Unik Sort', 'youtube_id' => 'a2026', 'air_date' => '2026-12-31']);

        Livewire::test(VideosIndex::class)
            ->set('search', 'Unik Sort')
            ->set('sort', 'latest')
            ->assertSeeInOrder(['Tayangan Anyar 2026 Unik Sort', 'Tayangan Jadul 2019 Unik Sort']);
    }

    /**
     * Test 9: Title sorting works.
     */
    public function test_title_sorting_works(): void
    {
        Video::create(['program_id' => null, 'user_id' => $this->contentAuthor()->id, 'title' => 'Alpha Video Unik Sort', 'youtube_id' => 'ytA', 'air_date' => now()->toDateString()]);
        Video::create(['program_id' => null, 'user_id' => $this->contentAuthor()->id, 'title' => 'Omega Video Unik Sort', 'youtube_id' => 'ytZ', 'air_date' => now()->toDateString()]);

        Livewire::test(VideosIndex::class)
            ->set('search', 'Video Unik Sort')
            ->set('sort', 'title_asc')
            ->assertSeeInOrder(['Alpha Video Unik Sort', 'Omega Video Unik Sort']);

        Livewire::test(VideosIndex::class)
            ->set('search', 'Video Unik Sort')
            ->set('sort', 'title_desc')
            ->assertSeeInOrder(['Omega Video Unik Sort', 'Alpha Video Unik Sort']);
    }

    /**
     * Test 10: Pagination works on video listing.
     */
    public function test_pagination_works(): void
    {
        for ($i = 1; $i <= 15; $i++) {
            Video::create([
                'program_id' => null,
                'user_id' => $this->contentAuthor()->id,
                'title' => "Batch Video Pagination {$i}",
                'youtube_id' => "batch_pag_{$i}",
                'air_date' => now()->toDateString(),
            ]);
        }

        Livewire::test(VideosIndex::class)
            ->assertViewHas('videos', function ($videos) {
                return $videos->total() >= 15 && $videos->count() === 12;
            });
    }

    /**
     * Test 11: Video detail page loads with HTTP 200.
     */
    public function test_detail_page_loads(): void
    {
        $video = Video::create([
            'program_id' => null,
            'user_id' => $this->contentAuthor()->id,
            'title' => 'Tayangan Eksklusif Uji Coba Halaman Detail',
            'youtube_id' => 'detailTest999',
            'air_date' => now()->toDateString(),
        ]);

        $response = $this->get(route('video.show', $video));

        $response->assertStatus(200);
        $response->assertSee('Tayangan Eksklusif Uji Coba Halaman Detail');
    }

    /**
     * Test 12: Video title displays correctly on show page.
     */
    public function test_title_displays(): void
    {
        $video = Video::create([
            'program_id' => null,
            'user_id' => $this->contentAuthor()->id,
            'title' => 'Judul Video Yang Sangat Jelas Terbaca',
            'youtube_id' => 'titleDisp123',
            'air_date' => now()->toDateString(),
        ]);

        Livewire::test(VideosShow::class, ['video' => $video])
            ->assertSee('Judul Video Yang Sangat Jelas Terbaca');
    }

    /**
     * Test 13: YouTube Embed URL is rendered in iframe.
     */
    public function test_embed_url_is_used(): void
    {
        $video = Video::create([
            'program_id' => null,
            'user_id' => $this->contentAuthor()->id,
            'title' => 'Video Embed Testing',
            'youtube_id' => 'embedYtKey888',
            'air_date' => now()->toDateString(),
        ]);

        $response = $this->get(route('video.show', $video));

        $response->assertStatus(200);
        $response->assertSee('https://www.youtube.com/embed/embedYtKey888');
    }

    /**
     * Test 14: Program relationship is rendered when available.
     */
    public function test_program_relationship_displays(): void
    {
        $program = Program::create([
            'name' => 'WPiTV Eksklusif Studio',
            'slug' => 'wpitv-eksklusif-studio',
            'status' => true,
        ]);

        $video = Video::create([
            'program_id' => $program->id,
            'user_id' => $this->contentAuthor()->id,
            'title' => 'Episode Perdana Studio',
            'youtube_id' => 'studioEp1',
            'air_date' => now()->toDateString(),
        ]);

        Livewire::test(VideosShow::class, ['video' => $video])
            ->assertSee('WPiTV Eksklusif Studio')
            ->assertSee(route('program.show', $program->slug));
    }

    /**
     * Test 15: NULL program is handled gracefully without crashing.
     */
    public function test_null_program_handled(): void
    {
        $video = Video::create([
            'program_id' => null,
            'user_id' => $this->contentAuthor()->id,
            'title' => 'Video Lepas Tanpa Program Induk',
            'youtube_id' => 'noProgHandle',
            'air_date' => now()->toDateString(),
        ]);

        Livewire::test(VideosShow::class, ['video' => $video])
            ->assertSee('Video Lepas Tanpa Program Induk');
    }

    /**
     * Test 16: NULL user_id is handled gracefully.
     */
    public function test_null_user_handled(): void
    {
        $video = Video::create([
            'program_id' => null,
            'user_id' => null,
            'title' => 'Video Tanpa User Pengunggah',
            'youtube_id' => 'noUserHandle',
            'air_date' => now()->toDateString(),
        ]);

        Livewire::test(VideosShow::class, ['video' => $video])
            ->assertSee('Video Tanpa User Pengunggah');
    }

    /**
     * Test 17: NULL description does not break layout.
     */
    public function test_null_description_handled(): void
    {
        $video = Video::create([
            'program_id' => null,
            'user_id' => $this->contentAuthor()->id,
            'title' => 'Video Deskripsi Null',
            'youtube_id' => 'nullDescVid',
            'description' => null,
            'air_date' => now()->toDateString(),
        ]);

        Livewire::test(VideosShow::class, ['video' => $video])
            ->assertSee('Video Deskripsi Null');
    }

    /**
     * Test 18: Related videos are displayed on show page.
     */
    public function test_related_videos_display(): void
    {
        $program = Program::create(['name' => 'Program Terkait Test', 'slug' => 'program-terkait-test', 'status' => true]);

        $video1 = Video::create([
            'program_id' => $program->id,
            'user_id' => $this->contentAuthor()->id,
            'title' => 'Video Utama Yang Sedang Ditonton',
            'youtube_id' => 'relMainVid',
            'air_date' => now()->toDateString(),
        ]);

        $video2 = Video::create([
            'program_id' => $program->id,
            'user_id' => $this->contentAuthor()->id,
            'title' => 'Video Terkait Satu Program',
            'youtube_id' => 'relSameProg',
            'air_date' => now()->subDay()->toDateString(),
        ]);

        Livewire::test(VideosShow::class, ['video' => $video1])
            ->assertSee('Tayangan Terkait')
            ->assertSee('Video Terkait Satu Program');
    }

    /**
     * Test 19: Duplicate youtube_id records remain valid and can be viewed.
     */
    public function test_duplicate_youtube_id_remains_valid(): void
    {
        $vidA = Video::create(['program_id' => null, 'user_id' => $this->contentAuthor()->id, 'title' => 'Duplikat ID Pertama', 'youtube_id' => 'sameYtId123', 'air_date' => now()->toDateString()]);
        $vidB = Video::create(['program_id' => null, 'user_id' => $this->contentAuthor()->id, 'title' => 'Duplikat ID Kedua', 'youtube_id' => 'sameYtId123', 'air_date' => now()->toDateString()]);

        $this->get(route('video.show', $vidA))->assertStatus(200)->assertSee('Duplikat ID Pertama');
        $this->get(route('video.show', $vidB))->assertStatus(200)->assertSee('Duplikat ID Kedua');
    }

    /**
     * Test 20: Homepage video links use internal video.show route.
     */
    public function test_homepage_links_use_internal_video_route(): void
    {
        $video = Video::create(['title' => 'Video Uji Tautan', 'user_id' => $this->contentAuthor()->id, 'youtube_id' => 'testVideo01', 'air_date' => now()->toDateString()]);

        $response = $this->get(route('public.home'));

        $response->assertStatus(200);
        $response->assertSee(route('video.index'));
        if ($video) {
            $response->assertSee(route('video.show', $video));
        }
    }

    /**
     * Test 21: Program detail links use internal video.show route.
     */
    public function test_program_detail_links_use_internal_video_route(): void
    {
        $program = Program::where('status', true)->first();
        if (! $program) {
            $program = Program::create(['name' => 'Program Link Test', 'slug' => 'program-link-test', 'status' => true]);
        }

        $video = Video::create([
            'program_id' => $program->id,
            'user_id' => $this->contentAuthor()->id,
            'title' => 'Video Link Internal Program Test',
            'youtube_id' => 'intLinkProg99',
            'air_date' => now()->toDateString(),
        ]);

        $response = $this->get(route('program.show', $program->slug));

        $response->assertStatus(200);
        $response->assertSee(route('video.show', $video));
    }

    /**
     * Test 22: Guest can access video index and show routes.
     */
    public function test_guest_can_access(): void
    {
        $this->assertGuest();

        $this->get(route('video.index'))->assertStatus(200);

        $video = Video::first() ?? Video::create([
            'program_id' => null,
            'user_id' => $this->contentAuthor()->id,
            'title' => 'Guest Accessible Video',
            'youtube_id' => 'guestVid123',
            'air_date' => now()->toDateString(),
        ]);

        $this->get(route('video.show', $video))->assertStatus(200);
    }

    /**
     * Test 23: No admin management actions exposed on public video pages.
     */
    public function test_no_admin_action_exposed(): void
    {
        $video = Video::first() ?? Video::create([
            'program_id' => null,
            'user_id' => $this->contentAuthor()->id,
            'title' => 'Security Test Video',
            'youtube_id' => 'secVid123',
            'air_date' => now()->toDateString(),
        ]);

        $responseIndex = $this->get(route('video.index'));
        $responseIndex->assertStatus(200);
        $responseIndex->assertDontSee('admin.videos.create');
        $responseIndex->assertDontSee('admin.videos.edit');

        $responseShow = $this->get(route('video.show', $video));
        $responseShow->assertStatus(200);
        $responseShow->assertDontSee('admin.videos.create');
        $responseShow->assertDontSee('admin.videos.edit');
    }

    /**
     * Test 24: Video detail contains correct deterministic grid layout structure.
     */
    public function test_video_detail_layout_structure_and_responsive_classes(): void
    {
        $video = Video::first() ?? Video::create([
            'program_id' => null,
            'user_id' => $this->contentAuthor()->id,
            'title' => 'Layout Verification Video',
            'youtube_id' => 'layoutCheck123',
            'air_date' => now()->toDateString(),
        ]);

        Video::create(['program_id' => $video->program_id, 'user_id' => $this->contentAuthor()->id,
            'title' => 'Related Layout Test', 'youtube_id' => 'testVideo02', 'air_date' => now()->toDateString()]);

        $response = $this->get(route('video.show', $video));

        $response->assertStatus(200);
        $response->assertSee('video-detail-layout', false);
        $response->assertSee('video-detail-main', false);
        $response->assertSee('video-player-wrap', false);
        $response->assertSee('video-info-card', false);
        $response->assertSee('video-detail-sidebar', false);
        $response->assertSee('related-item', false);
        $response->assertSee('related-thumb', false);
        $response->assertSee('Tayangan Terkait');
    }
}
