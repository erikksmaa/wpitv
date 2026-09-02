<?php

namespace Tests\Feature\Public;

use App\Livewire\Public\Programs\Index as ProgramsIndex;
use App\Livewire\Public\Programs\Show as ProgramsShow;
use App\Models\Program;
use App\Models\TVClubGeneration;
use App\Models\TVClubGroup;
use App\Models\TVClubMember;
use App\Models\Video;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class ProgramPagesTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Test 1: Program index accessible by guest without authentication.
     */
    public function test_program_index_accessible_by_guest(): void
    {
        $this->assertGuest();

        $response = $this->get(route('program.index'));

        $response->assertStatus(200);
        $response->assertSee('Program Acara');
        $response->assertSee('Temukan berbagai program dan tayangan produksi WPiTV.');
    }

    /**
     * Test 2: Active programs are rendered on index.
     */
    public function test_active_programs_rendered(): void
    {
        $program = Program::create([
            'name' => 'Program Aktif WPiTV 2026',
            'slug' => 'program-aktif-wpitv-2026',
            'description' => 'Deskripsi program aktif.',
            'status' => true,
        ]);

        Livewire::test(ProgramsIndex::class)
            ->assertSee('Program Aktif WPiTV 2026');
    }

    /**
     * Test 3: Inactive programs are NOT rendered on index.
     */
    public function test_inactive_programs_not_rendered(): void
    {
        $inactive = Program::create([
            'name' => 'Program Nonaktif Rahasia',
            'slug' => 'program-nonaktif-rahasia',
            'description' => 'Program yang sedang di-draft.',
            'status' => false,
        ]);

        Livewire::test(ProgramsIndex::class)
            ->assertDontSee('Program Nonaktif Rahasia');
    }

    /**
     * Test 4: Program search filter works.
     */
    public function test_program_search_works(): void
    {
        $progA = Program::create([
            'name' => 'Cakrawala Inovasi Teknologi',
            'slug' => 'cakrawala-inovasi-teknologi',
            'description' => 'Program teknologi kampus.',
            'status' => true,
        ]);

        $progB = Program::create([
            'name' => 'Suara Komunitas Pekalongan',
            'slug' => 'suara-komunitas-pekalongan',
            'description' => 'Program komunitas lokal.',
            'status' => true,
        ]);

        Livewire::test(ProgramsIndex::class)
            ->set('search', 'Cakrawala Inovasi')
            ->assertSee('Cakrawala Inovasi Teknologi')
            ->assertDontSee('Suara Komunitas Pekalongan');
    }

    /**
     * Test 5: Sort by name ASC & DESC works.
     */
    public function test_sort_by_name_works(): void
    {
        Program::create(['name' => 'AAA Program Pertama', 'slug' => 'aaa-program-pertama', 'status' => true]);
        Program::create(['name' => 'ZZZ Program Terakhir', 'slug' => 'zzz-program-terakhir', 'status' => true]);

        Livewire::test(ProgramsIndex::class)
            ->set('sort', 'name_asc')
            ->assertSee('AAA Program Pertama');

        Livewire::test(ProgramsIndex::class)
            ->set('sort', 'name_desc')
            ->assertSee('ZZZ Program Terakhir');
    }

    /**
     * Test 6: Sort by newest works.
     */
    public function test_sort_by_newest_works(): void
    {
        $newest = Program::create([
            'name' => 'Program Paling Baru Terbit',
            'slug' => 'program-paling-baru-terbit',
            'status' => true,
            'created_at' => now()->addMinutes(10),
        ]);

        Livewire::test(ProgramsIndex::class)
            ->set('sort', 'latest')
            ->assertSee('Program Paling Baru Terbit');
    }

    /**
     * Test 7: Sort by videos count works.
     */
    public function test_sort_by_videos_count_works(): void
    {
        $prog = Program::create([
            'name' => 'Program Mega Hits',
            'slug' => 'program-mega-hits',
            'status' => true,
        ]);

        for ($i = 0; $i < 300; $i++) {
            Video::create([
                'program_id' => $prog->id,
                'user_id' => $this->contentAuthor()->id,
                'title' => "Mega Ep {$i}",
                'youtube_id' => "vid_mega_{$i}",
                'air_date' => now()->toDateString(),
            ]);
        }

        Livewire::test(ProgramsIndex::class)
            ->set('sort', 'most_videos')
            ->assertSee('Program Mega Hits')
            ->assertSee('300 Tayangan');
    }

    /**
     * Test 8: Pagination works on program index.
     */
    public function test_pagination_works(): void
    {
        for ($i = 1; $i <= 15; $i++) {
            Program::create([
                'name' => "Batch Pagination Program {$i}",
                'slug' => "batch-pagination-program-{$i}",
                'status' => true,
            ]);
        }

        Livewire::test(ProgramsIndex::class)
            ->assertViewHas('programs', function ($programs) {
                return $programs->total() >= 15;
            });
    }

    /**
     * Test 9: Active program detail loads by slug.
     */
    public function test_active_program_detail_loads_by_slug(): void
    {
        $program = Program::create([
            'name' => 'Edisi Bincang Santai Kampus',
            'slug' => 'edisi-bincang-santai-kampus',
            'description' => 'Deskripsi bincang santai kampus detail.',
            'status' => true,
        ]);

        $response = $this->get(route('program.show', $program->slug));

        $response->assertStatus(200);
        $response->assertSee('Edisi Bincang Santai Kampus');
        $response->assertSee('Deskripsi bincang santai kampus detail.');
    }

    /**
     * Test 10: Inactive program returns 404.
     */
    public function test_inactive_program_returns_404(): void
    {
        $program = Program::create([
            'name' => 'Program Tersembunyi Draft',
            'slug' => 'program-tersembunyi-draft',
            'status' => false,
        ]);

        $response = $this->get(route('program.show', $program->slug));

        $response->assertStatus(404);
    }

    /**
     * Test 11: Invalid slug returns 404.
     */
    public function test_invalid_slug_returns_404(): void
    {
        $response = $this->get('/program/slug-yang-tidak-pernah-ada-9999');

        $response->assertStatus(404);
    }

    /**
     * Test 12: Program description renders properly.
     */
    public function test_program_description_renders(): void
    {
        $program = Program::create([
            'name' => 'Program Dengan Deskripsi Panjang',
            'slug' => 'program-dengan-deskripsi-panjang',
            'description' => 'Program ini mengupas tuntas kegiatan riset dan inovasi mahasiswa.',
            'status' => true,
        ]);

        Livewire::test(ProgramsShow::class, ['program' => $program])
            ->assertSee('Program ini mengupas tuntas kegiatan riset dan inovasi mahasiswa.');
    }

    /**
     * Test 13: Program image fallback works.
     */
    public function test_program_image_fallback_works(): void
    {
        $program = Program::create([
            'name' => 'Program Gambar Kosong',
            'slug' => 'program-gambar-kosong',
            'image' => null,
            'status' => true,
        ]);

        Livewire::test(ProgramsShow::class, ['program' => $program])
            ->assertSee('program-placeholder.svg');
    }

    /**
     * Test 14: Videos belonging to program render in video list.
     */
    public function test_videos_belonging_to_program_render(): void
    {
        $program = Program::create([
            'name' => 'WPiTV Buletin Pagi',
            'slug' => 'wpitv-buletin-pagi',
            'status' => true,
        ]);

        Video::create([
            'program_id' => $program->id,
            'user_id' => $this->contentAuthor()->id,
            'title' => 'Buletin Pagi Edisi 10 Februari 2026',
            'youtube_id' => 'pagi10feb26',
            'air_date' => now()->toDateString(),
        ]);

        Livewire::test(ProgramsShow::class, ['program' => $program])
            ->assertSee('Buletin Pagi Edisi 10 Februari 2026')
            ->assertSee('pagi10feb26');
    }

    /**
     * Test 15: Videos from other program do not render in this program detail.
     */
    public function test_videos_from_other_program_do_not_render(): void
    {
        $programA = Program::create(['name' => 'Program A Alpha', 'slug' => 'program-a-alpha', 'status' => true]);
        $programB = Program::create(['name' => 'Program B Beta', 'slug' => 'program-b-beta', 'status' => true]);

        Video::create([
            'program_id' => $programB->id,
            'user_id' => $this->contentAuthor()->id,
            'title' => 'Video Khusus Milik Program Beta Saja',
            'youtube_id' => 'betaOnly999',
            'air_date' => now()->toDateString(),
        ]);

        Livewire::test(ProgramsShow::class, ['program' => $programA])
            ->assertDontSee('Video Khusus Milik Program Beta Saja');
    }

    /**
     * Test 16: Program with no videos handles empty state gracefully.
     */
    public function test_program_with_no_videos_handles_empty_state(): void
    {
        $program = Program::create([
            'name' => 'Program Baru Tanpa Video',
            'slug' => 'program-baru-tanpa-video',
            'status' => true,
        ]);

        Livewire::test(ProgramsShow::class, ['program' => $program])
            ->assertSee('Belum ada tayangan untuk program ini.');
    }

    /**
     * Test 17: Videos are ordered by air_date descending.
     */
    public function test_videos_ordered_by_air_date(): void
    {
        $program = Program::create([
            'name' => 'Program Serial Urut',
            'slug' => 'program-serial-urut',
            'status' => true,
        ]);

        Video::create([
            'program_id' => $program->id,
            'user_id' => $this->contentAuthor()->id,
            'title' => 'Video Lama 2024',
            'youtube_id' => 'lama2024',
            'air_date' => '2024-01-01',
        ]);

        Video::create([
            'program_id' => $program->id,
            'user_id' => $this->contentAuthor()->id,
            'title' => 'Video Baru 2026',
            'youtube_id' => 'baru2026',
            'air_date' => '2026-01-01',
        ]);

        Livewire::test(ProgramsShow::class, ['program' => $program])
            ->assertSeeInOrder(['Video Baru 2026', 'Video Lama 2024']);
    }

    /**
     * Test 18: Video pagination works on program detail.
     */
    public function test_video_pagination_works(): void
    {
        $program = Program::create([
            'name' => 'Program Ratusan Episode',
            'slug' => 'program-ratusan-episode',
            'status' => true,
        ]);

        for ($i = 1; $i <= 15; $i++) {
            Video::create([
                'program_id' => $program->id,
                'user_id' => $this->contentAuthor()->id,
                'title' => "Episode Ratusan {$i}",
                'youtube_id' => "ratusan_{$i}",
                'air_date' => now()->toDateString(),
            ]);
        }

        Livewire::test(ProgramsShow::class, ['program' => $program])
            ->assertViewHas('videos', function ($videos) {
                return $videos->total() === 15 && $videos->count() === 12;
            });
    }

    /**
     * Test 19: Other programs section renders active programs.
     */
    public function test_other_programs_section_works(): void
    {
        $current = Program::create(['name' => 'Program Utama Dibuka', 'slug' => 'program-utama-dibuka', 'status' => true]);
        $other = Program::create(['name' => 'Program Pendukung Kampus', 'slug' => 'program-pendukung-kampus', 'status' => true]);

        Livewire::test(ProgramsShow::class, ['program' => $current])
            ->assertSee('Program Lainnya')
            ->assertSee('Program Pendukung Kampus');
    }

    /**
     * Test 20: Homepage program links use public detail route.
     */
    public function test_homepage_program_links_use_public_detail_route(): void
    {
        $program = Program::create(['name' => 'Program Uji Tautan', 'slug' => 'program-uji-tautan', 'status' => true]);

        $response = $this->get(route('public.home'));

        $response->assertStatus(200);
        $response->assertSee(route('program.index'));
        if ($program) {
            $response->assertSee(route('program.show', $program->slug));
        }
    }

    /**
     * Test 21: No admin management actions exposed on public program pages.
     */
    public function test_no_admin_actions_exposed(): void
    {
        $program = Program::where('status', true)->first() ?? Program::create([
            'name' => 'Sample Program Test',
            'slug' => 'sample-program-test',
            'status' => true,
        ]);

        $responseIndex = $this->get(route('program.index'));
        $responseIndex->assertStatus(200);
        $responseIndex->assertDontSee('admin.programs.create');
        $responseIndex->assertDontSee('admin.programs.edit');

        $responseShow = $this->get(route('program.show', $program->slug));
        $responseShow->assertStatus(200);
        $responseShow->assertDontSee('admin.programs.create');
        $responseShow->assertDontSee('admin.programs.edit');
    }

    /**
     * Test 22: Public page does not require authentication.
     */
    public function test_public_page_does_not_require_authentication(): void
    {
        $this->assertGuest();

        $this->get(route('program.index'))->assertStatus(200);

        $program = Program::create(['name' => 'Program Uji Tamu', 'slug' => 'program-uji-tamu', 'status' => true]);
        if ($program) {
            $this->get(route('program.show', $program->slug))->assertStatus(200);
        }
    }

    /**
     * Test 23: Existing homepage remains fully functional.
     */
    public function test_existing_homepage_remains_functional(): void
    {
        $response = $this->get(route('public.home'));

        $response->assertStatus(200);
        $response->assertSee('Cerita Kampus.');
        $response->assertSee('Program Acara');
        $response->assertSee('Tayangan Terbaru');
        $response->assertSee('Di Balik Layar WPiTV');
    }

    public function test_program_crew_renders_actual_role_generation_and_member_link_without_private_data(): void
    {
        $program = Program::create([
            'name' => 'Program Kru Editorial',
            'slug' => 'program-kru-editorial',
            'status' => true,
        ]);
        $generation = TVClubGeneration::create([
            'name' => 'Generasi Editorial 2041',
            'year' => 2041,
            'status' => true,
        ]);
        $group = TVClubGroup::create([
            'generation_id' => $generation->id,
            'program_id' => $program->id,
            'name' => 'Tim Produksi Editorial',
            'status' => true,
        ]);
        $member = TVClubMember::create([
            'generation_id' => $generation->id,
            'student_number' => 'NIM-PRIVATE-2041',
            'name' => 'Aulia Rahma Editorial',
            'photo' => null,
            'status' => true,
        ]);
        $group->members()->attach($member->id, ['role' => 'Camera Operator']);

        Livewire::test(ProgramsShow::class, ['program' => $program])
            ->assertSee('Tim di Balik Program')
            ->assertSee('Aulia Rahma Editorial')
            ->assertSee('Camera Operator')
            ->assertSee('Generasi Editorial 2041')
            ->assertSee(route('tvclub.members.show', $member->id))
            ->assertSee('AR')
            ->assertDontSee('NIM-PRIVATE-2041');
    }

    public function test_null_or_blank_pivot_role_falls_back_to_anggota_kru(): void
    {
        $program = Program::create(['name' => 'Program Role Kosong', 'slug' => 'program-role-kosong', 'status' => true]);
        $generation = TVClubGeneration::create(['name' => 'Generasi Role 2042', 'year' => 2042, 'status' => true]);
        $group = TVClubGroup::create([
            'generation_id' => $generation->id,
            'program_id' => $program->id,
            'name' => 'Tim Role Kosong',
            'status' => true,
        ]);
        $member = TVClubMember::create([
            'generation_id' => $generation->id,
            'student_number' => 'ROLE-EMPTY-2042',
            'name' => 'Bagas Role Kosong',
            'status' => true,
        ]);
        $group->members()->attach($member->id, ['role' => null]);

        Livewire::test(ProgramsShow::class, ['program' => $program])
            ->assertSee('Bagas Role Kosong')
            ->assertSee('Anggota Kru');
    }

    public function test_program_without_crew_or_with_only_empty_groups_hides_crew_section(): void
    {
        $program = Program::create(['name' => 'Program Tanpa Kru', 'slug' => 'program-tanpa-kru', 'status' => true]);
        $generation = TVClubGeneration::create(['name' => 'Generasi Kosong 2043', 'year' => 2043, 'status' => true]);
        TVClubGroup::create([
            'generation_id' => $generation->id,
            'program_id' => $program->id,
            'name' => 'Kelompok Tanpa Anggota',
            'status' => true,
        ]);

        Livewire::test(ProgramsShow::class, ['program' => $program])
            ->assertDontSee('Tim di Balik Program')
            ->assertDontSee('Kelompok Tanpa Anggota')
            ->assertViewHas('productionGroups', fn ($groups): bool => $groups->isEmpty());
    }

    public function test_multiple_groups_prioritize_active_newest_generation_and_keep_history_accessible(): void
    {
        $program = Program::create(['name' => 'Program Lintas Generasi', 'slug' => 'program-lintas-generasi', 'status' => true]);

        $groups = collect([
            ['name' => 'Generasi Aktif Lama 2044', 'year' => 2044, 'group' => 'Tim Aktif Lama', 'status' => true, 'member' => 'Kru Aktif Lama'],
            ['name' => 'Generasi Aktif Baru 2045', 'year' => 2045, 'group' => 'Tim Aktif Terbaru', 'status' => true, 'member' => 'Kru Aktif Terbaru'],
            ['name' => 'Generasi Historis 2046', 'year' => 2046, 'group' => 'Tim Historis', 'status' => false, 'member' => 'Kru Historis'],
        ])->map(function (array $data) use ($program): TVClubGroup {
            $generation = TVClubGeneration::create([
                'name' => $data['name'],
                'year' => $data['year'],
                'status' => $data['status'],
            ]);
            $group = TVClubGroup::create([
                'generation_id' => $generation->id,
                'program_id' => $program->id,
                'name' => $data['group'],
                'status' => $data['status'],
            ]);
            $member = TVClubMember::create([
                'generation_id' => $generation->id,
                'student_number' => 'MULTI-'.$data['year'],
                'name' => $data['member'],
                'status' => $data['status'],
            ]);
            $group->members()->attach($member->id, ['role' => 'Creative']);

            return $group;
        });

        $newestActive = $groups->firstWhere('name', 'Tim Aktif Terbaru');

        Livewire::test(ProgramsShow::class, ['program' => $program])
            ->assertViewHas('defaultProductionGroup', fn ($group): bool => $group->is($newestActive))
            ->assertViewHas('productionGroups', function ($productionGroups) use ($newestActive): bool {
                return $productionGroups->count() === 3
                    && $productionGroups->first()->is($newestActive)
                    && $productionGroups->every(fn ($group): bool => $group->relationLoaded('generation') && $group->relationLoaded('members'));
            })
            ->assertSee('role="tablist"', false)
            ->assertSee('data-active-group="'.$newestActive->id.'"', false)
            ->assertSee('Kru Aktif Terbaru')
            ->assertSee('Kru Aktif Lama')
            ->assertSee('Kru Historis');
    }

    public function test_single_production_group_does_not_render_unnecessary_tabs(): void
    {
        $program = Program::create(['name' => 'Program Satu Tim', 'slug' => 'program-satu-tim', 'status' => true]);
        $generation = TVClubGeneration::create(['name' => 'Generasi Tunggal 2047', 'year' => 2047, 'status' => true]);
        $group = TVClubGroup::create([
            'generation_id' => $generation->id,
            'program_id' => $program->id,
            'name' => 'Tim Produksi Tunggal',
            'status' => true,
        ]);
        $member = TVClubMember::create([
            'generation_id' => $generation->id,
            'student_number' => 'SINGLE-2047',
            'name' => 'Kru Produksi Tunggal',
            'status' => true,
        ]);
        $group->members()->attach($member->id, ['role' => 'Editor']);

        Livewire::test(ProgramsShow::class, ['program' => $program])
            ->assertSee('Tim Produksi Tunggal')
            ->assertDontSee('role="tablist"', false)
            ->assertDontSee('data-program-crew-tab', false);
    }

    public function test_empty_description_is_omitted_and_video_count_uses_actual_data(): void
    {
        $program = Program::create([
            'name' => 'Program Tanpa Deskripsi Sistem',
            'slug' => 'program-tanpa-deskripsi-sistem',
            'description' => null,
            'status' => true,
        ]);

        foreach (range(1, 2) as $episode) {
            Video::create([
                'program_id' => $program->id,
                'user_id' => $this->contentAuthor()->id,
                'title' => 'Episode Hitung Aktual '.$episode,
                'youtube_id' => 'countActual'.$episode,
                'air_date' => now()->toDateString(),
            ]);
        }

        Livewire::test(ProgramsShow::class, ['program' => $program])
            ->assertSee('2 Tayangan')
            ->assertDontSee('Tayangan Diproduksi')
            ->assertDontSee('Belum ada deskripsi untuk program acara ini.');
    }
}
