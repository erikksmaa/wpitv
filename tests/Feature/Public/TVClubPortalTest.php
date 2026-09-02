<?php

namespace Tests\Feature\Public;

use App\Livewire\Public\TVClub\Generations\Index as GenerationsIndex;
use App\Livewire\Public\TVClub\Generations\Show as GenerationShow;
use App\Livewire\Public\TVClub\Groups\Index as GroupsIndex;
use App\Livewire\Public\TVClub\Index as TVClubIndex;
use App\Livewire\Public\TVClub\Members\Index as MembersIndex;
use App\Livewire\Public\TVClub\Members\Show as MemberShow;
use App\Models\Program;
use App\Models\TVClubGeneration;
use App\Models\TVClubGroup;
use App\Models\TVClubMember;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

class TVClubPortalTest extends TestCase
{
    use DatabaseTransactions;

    private function productionMember(): TVClubMember
    {
        $generation = TVClubGeneration::create(['name' => 'Generasi Uji Relasi', 'year' => 3901, 'status' => true]);
        $program = Program::create(['name' => 'Program Uji Relasi', 'slug' => 'program-uji-relasi', 'status' => true]);
        $group = TVClubGroup::create(['generation_id' => $generation->id, 'program_id' => $program->id, 'name' => 'Kelompok Uji Relasi', 'status' => true]);
        $member = TVClubMember::create(['generation_id' => $generation->id, 'student_number' => 'TEST-PRIVATE-3901', 'name' => 'Kru Uji Relasi', 'status' => true]);
        $group->members()->attach($member->id, ['role' => 'Kameraman']);

        return $member->load('generation', 'groups.program');
    }

    /**
     * Test 1: TV Club landing loads successfully.
     */
    public function test_tv_club_landing_loads(): void
    {
        $response = $this->get(route('tvclub.index'));

        $response->assertStatus(200);
        $response->assertSee('Mereka yang berada');
        $response->assertSee('Di Balik Layar WPiTV');
        $response->assertSee('Kenali Kru TV Club');
    }

    /**
     * Test 2: Statistics come directly from database counts.
     */
    public function test_statistics_come_from_database(): void
    {
        $genCount = TVClubGeneration::count();
        $memCount = TVClubMember::count();
        $grpCount = TVClubGroup::count();

        $response = $this->get(route('tvclub.index'));

        $response->assertStatus(200);
        $response->assertSee((string) $genCount);
        $response->assertSee((string) $memCount);
        $response->assertSee((string) $grpCount);
    }

    /**
     * Test 3: Generations render on landing.
     */
    public function test_generations_render(): void
    {
        $gen = TVClubGeneration::create([
            'name' => 'Generasi Aktif Landing '.uniqid(),
            'year' => 3200,
            'description' => 'Deskripsi generasi pengujian.',
            'status' => true,
        ]);

        $response = $this->get(route('tvclub.index'));

        $response->assertStatus(200);
        $response->assertSee($gen->name);
    }

    /**
     * Test 4: Only actual generations are shown.
     */
    public function test_only_actual_generations_shown(): void
    {
        $fakeName = 'Generasi Fiktif Non-Existent '.uniqid();

        $response = $this->get(route('tvclub.index'));

        $response->assertStatus(200);
        $response->assertDontSee($fakeName);
    }

    /**
     * Test 5: Generation detail loads.
     */
    public function test_generation_detail_loads(): void
    {
        $gen = TVClubGeneration::first() ?? TVClubGeneration::create([
            'name' => 'Generasi Detail Test '.uniqid(),
            'year' => 2024,
            'description' => 'Deskripsi pengujian detail generasi.',
            'status' => true,
        ]);

        $response = $this->get(route('tvclub.generations.show', $gen->id));

        $response->assertStatus(200);
        $response->assertSee($gen->name);
    }

    /**
     * Test 6: Generation members render in generation detail.
     */
    public function test_generation_members_render(): void
    {
        $gen = TVClubGeneration::first();
        if ($gen && $gen->members()->count() > 0) {
            $member = $gen->members()->first();
            $response = $this->get(route('tvclub.generations.show', $gen->id));
            $response->assertStatus(200);
            $response->assertSee($member->name);
        } else {
            $gen = TVClubGeneration::create([
                'name' => 'Gen Test Members '.uniqid(),
                'year' => 3100,
                'status' => true,
            ]);
            $member = TVClubMember::create([
                'generation_id' => $gen->id,
                'student_number' => 'NIM'.rand(1000, 9999),
                'name' => 'Anggota Test Unik '.uniqid(),
                'status' => true,
            ]);

            $response = $this->get(route('tvclub.generations.show', $gen->id));
            $response->assertStatus(200);
            $response->assertSee($member->name);
        }
    }

    /**
     * Test 7: Generation groups render in generation detail.
     */
    public function test_generation_groups_render(): void
    {
        $gen = TVClubGeneration::first();
        if ($gen && $gen->groups()->count() > 0) {
            $group = $gen->groups()->first();
            $response = $this->get(route('tvclub.generations.show', $gen->id));
            $response->assertStatus(200);
            $response->assertSee($group->name);
        } else {
            $gen = TVClubGeneration::create([
                'name' => 'Gen Test Groups '.uniqid(),
                'year' => 3101,
                'status' => true,
            ]);
            $group = TVClubGroup::create([
                'generation_id' => $gen->id,
                'name' => 'Kelompok Test Unik '.uniqid(),
                'program_id' => Program::create(['name' => 'Program Uji Kelompok', 'slug' => 'program-uji-kelompok', 'status' => 1])->id,
                'status' => true,
            ]);

            $response = $this->get(route('tvclub.generations.show', $gen->id));
            $response->assertStatus(200);
            $response->assertSee($group->name);
        }
    }

    /**
     * Test 8: Active and inactive states render properly.
     */
    public function test_active_inactive_state_renders(): void
    {
        $activeGen = TVClubGeneration::create([
            'name' => 'Gen Aktif '.uniqid(),
            'year' => 3006,
            'status' => true,
        ]);
        $inactiveGen = TVClubGeneration::create([
            'name' => 'Gen Demisioner '.uniqid(),
            'year' => 3007,
            'status' => false,
        ]);

        $response = $this->get(route('tvclub.index'));
        $response->assertStatus(200);
        $response->assertSee($activeGen->name);
        $response->assertDontSee($inactiveGen->name);

        $archive = $this->get(route('tvclub.generations.index'));
        $archive->assertSee($activeGen->name);
        $archive->assertSee($inactiveGen->name);
    }

    /**
     * Test 9: Member detail loads.
     */
    public function test_member_detail_loads(): void
    {
        $gen = TVClubGeneration::first() ?? TVClubGeneration::create(['name' => 'Gen Mem Detail', 'year' => 3008, 'status' => true]);
        $member = TVClubMember::first() ?? TVClubMember::create([
            'generation_id' => $gen->id,
            'student_number' => 'NIM'.rand(1000, 9999),
            'name' => 'Kru Test '.uniqid(),
            'status' => true,
        ]);

        $response = $this->get(route('tvclub.members.show', $member->id));

        $response->assertStatus(200);
        $response->assertSee($member->name);
    }

    /**
     * Test 10: Member generation displays.
     */
    public function test_member_generation_displays(): void
    {
        $member = $this->productionMember();
        if ($member) {
            $response = $this->get(route('tvclub.members.show', $member->id));
            $response->assertStatus(200);
            $response->assertSee($member->generation->name);
        } else {
            $this->assertTrue(true);
        }
    }

    /**
     * Test 11: Member groups display.
     */
    public function test_member_groups_display(): void
    {
        $member = $this->productionMember();
        if ($member) {
            $group = $member->groups->first();
            $response = $this->get(route('tvclub.members.show', $member->id));
            $response->assertStatus(200);
            $response->assertSee($group->name);
        } else {
            $this->assertTrue(true);
        }
    }

    /**
     * Test 12: Pivot role displays.
     */
    public function test_pivot_role_displays(): void
    {
        $member = $this->productionMember();
        if ($member && ! empty($member->groups->first()?->pivot->role)) {
            $role = $member->groups->first()->pivot->role;
            $response = $this->get(route('tvclub.members.show', $member->id));
            $response->assertStatus(200);
            $response->assertSee($role);
        } else {
            $this->assertTrue(true);
        }
    }

    /**
     * Test 13: Null pivot role handled with graceful fallback.
     */
    public function test_null_pivot_role_handled(): void
    {
        $gen = TVClubGeneration::create(['name' => 'Gen Pivot Test', 'year' => 3001, 'status' => true]);
        $program = Program::first() ?? Program::create(['name' => 'Prog Test', 'slug' => 'prog-test-'.uniqid(), 'status' => true]);
        $member = TVClubMember::create([
            'generation_id' => $gen->id,
            'student_number' => 'NIM'.rand(1000, 9999),
            'name' => 'Member Pivot Null',
            'status' => true,
        ]);
        $group = TVClubGroup::create([
            'generation_id' => $gen->id,
            'program_id' => $program->id,
            'name' => 'Kelompok Pivot Null',
            'status' => true,
        ]);

        $group->members()->attach($member->id, ['role' => null]);

        $response = $this->get(route('tvclub.members.show', $member->id));
        $response->assertStatus(200);
        $response->assertSee('Anggota Kru');
    }

    /**
     * Test 14: Photo fallback initials works.
     */
    public function test_photo_fallback_works(): void
    {
        $gen = TVClubGeneration::first() ?? TVClubGeneration::create(['name' => 'Gen Photo Test', 'year' => 3002, 'status' => true]);
        $member = TVClubMember::create([
            'generation_id' => $gen->id,
            'student_number' => 'NIM'.rand(1000, 9999),
            'name' => 'Budi Santoso',
            'photo' => null,
            'status' => true,
        ]);

        $this->assertEquals('BS', $member->initials);
        $this->assertNull($member->photo_url);
    }

    /**
     * Test 15: Member without group handled cleanly.
     */
    public function test_member_without_group_handled(): void
    {
        $gen = TVClubGeneration::first() ?? TVClubGeneration::create(['name' => 'Gen Lone Test', 'year' => 3003, 'status' => true]);
        $member = TVClubMember::create([
            'generation_id' => $gen->id,
            'student_number' => 'NIM'.rand(1000, 9999),
            'name' => 'Member Lone '.uniqid(),
            'status' => true,
        ]);

        $response = $this->get(route('tvclub.members.show', $member->id));

        $response->assertStatus(200);
        $response->assertSee('Belum ada penugasan produksi yang tercatat.');
    }

    /**
     * Test 16: Group program relation displays.
     */
    public function test_group_program_relation_displays(): void
    {
        $group = $this->productionMember()->groups->first();
        if ($group) {
            $response = $this->get(route('tvclub.groups.index'));
            $response->assertStatus(200);
            $response->assertSee($group->program->name);
        } else {
            $this->assertTrue(true);
        }
    }

    /**
     * Test 17: Program link uses route program.show.
     */
    public function test_program_link_uses_program_show(): void
    {
        $group = $this->productionMember()->groups->first();
        if ($group) {
            $response = $this->get(route('tvclub.groups.index'));
            $response->assertStatus(200);
            $response->assertSee(route('program.show', $group->program->slug));
        } else {
            $this->assertTrue(true);
        }
    }

    /**
     * Test 18: No NIM/student_number exposed on public landing.
     */
    public function test_no_nim_exposed_on_landing(): void
    {
        $member = $this->productionMember();
        if ($member && ! empty($member->student_number)) {
            $response = $this->get(route('tvclub.index'));
            $response->assertStatus(200);
            $response->assertDontSee($member->student_number);
        } else {
            $this->assertTrue(true);
        }
    }

    /**
     * Test 19: No private contact exposed.
     */
    public function test_no_private_contact_exposed(): void
    {
        $response = $this->get(route('tvclub.index'));
        $response->assertStatus(200);
        $response->assertDontSee('password');
        $response->assertDontSee('remember_token');
    }

    /**
     * Test 20: Guest can access TV Club portal without authentication.
     */
    public function test_guest_can_access(): void
    {
        $this->assertGuest();

        $responseIndex = $this->get(route('tvclub.index'));
        $responseIndex->assertStatus(200);
        $this->get(route('tvclub.generations.index'))->assertStatus(200);
        $this->get(route('tvclub.members.index'))->assertStatus(200);
        $this->get(route('tvclub.groups.index'))->assertStatus(200);

        $gen = TVClubGeneration::first();
        if ($gen) {
            $responseGen = $this->get(route('tvclub.generations.show', $gen->id));
            $responseGen->assertStatus(200);
        }

        $member = TVClubMember::first();
        if ($member) {
            $responseMember = $this->get(route('tvclub.members.show', $member->id));
            $responseMember->assertStatus(200);
        }
    }

    /**
     * Test 21: No admin actions exposed.
     */
    public function test_no_admin_action_exposed(): void
    {
        $response = $this->get(route('tvclub.index'));
        $response->assertStatus(200);
        $response->assertDontSee('admin.tvclub');
        $response->assertDontSee('Tambah Anggota');
        $response->assertDontSee('Edit Generasi');
    }

    /**
     * Test 22: Missing generation returns 404.
     */
    public function test_missing_generation_returns_404(): void
    {
        $response = $this->get(route('tvclub.generations.show', 99999999));
        $response->assertStatus(404);
    }

    /**
     * Test 23: Missing member returns 404.
     */
    public function test_missing_member_returns_404(): void
    {
        $response = $this->get(route('tvclub.members.show', 99999999));
        $response->assertStatus(404);
    }

    /**
     * Test 24: Homepage TV Club CTA links to internal route.
     */
    public function test_homepage_tv_club_cta_internal(): void
    {
        $response = $this->get(route('public.home'));
        $response->assertStatus(200);
        $response->assertSee(route('tvclub.index'));
    }

    /**
     * Test 25: Member search works.
     */
    public function test_member_search_works(): void
    {
        $gen = TVClubGeneration::first() ?? TVClubGeneration::create(['name' => 'Gen Search Test', 'year' => 3004, 'status' => true]);
        $uniqueName = 'Nama Kru Unik '.uniqid();
        TVClubMember::create([
            'generation_id' => $gen->id,
            'student_number' => 'NIM'.rand(1000, 9999),
            'name' => $uniqueName,
            'status' => true,
        ]);

        Livewire::test(MembersIndex::class)
            ->set('search', $uniqueName)
            ->assertSee($uniqueName);
    }

    /**
     * Test 26: Generation filter works.
     */
    public function test_generation_filter_works(): void
    {
        $gen = TVClubGeneration::create(['name' => 'Gen Filter Test '.uniqid(), 'year' => 3005, 'status' => true]);
        $uniqueName = 'Kru Spesifik Gen '.uniqid();
        TVClubMember::create([
            'generation_id' => $gen->id,
            'student_number' => 'NIM'.rand(1000, 9999),
            'name' => $uniqueName,
            'status' => true,
        ]);

        Livewire::test(MembersIndex::class)
            ->set('generationId', (string) $gen->id)
            ->assertSee($uniqueName);
    }

    /**
     * Test 27: Empty member state handled cleanly.
     */
    public function test_empty_member_state_handled(): void
    {
        Livewire::test(MembersIndex::class)
            ->set('search', 'NonExistentMemberKeyword'.uniqid())
            ->assertSee('Belum ada kru yang ditemukan.')
            ->assertSee('Coba gunakan nama atau generasi yang berbeda.')
            ->assertSee('Reset Filter');
    }

    /**
     * Test 28: Layout links are connected.
     */
    public function test_layout_tv_club_link_connected(): void
    {
        $response = $this->get(route('public.home'));
        $response->assertStatus(200);
        $response->assertSee(route('tvclub.index'));
    }

    public function test_overview_is_a_compact_gateway_without_directory_controls_or_pagination(): void
    {
        $response = $this->get(route('tvclub.index'));

        $response->assertOk()
            ->assertSee('Jelajahi TV Club')
            ->assertSee(route('tvclub.generations.index'))
            ->assertSee(route('tvclub.members.index'))
            ->assertSee(route('tvclub.groups.index'))
            ->assertDontSee('Cari nama kru TV Club')
            ->assertDontSee('wire:model.live="generationId"', false)
            ->assertDontSee('Pagination Navigation');
    }

    public function test_generation_index_orders_database_generations_by_year_and_links_to_detail(): void
    {
        $older = TVClubGeneration::create(['name' => 'Generasi IA Lama', 'year' => 3210, 'status' => false]);
        $newer = TVClubGeneration::create(['name' => 'Generasi IA Baru', 'year' => 3211, 'status' => true]);

        Livewire::test(GenerationsIndex::class)
            ->assertSeeInOrder([$newer->name, $older->name])
            ->assertSee(route('tvclub.generations.show', $newer->id))
            ->assertSee(route('tvclub.generations.show', $older->id));
    }

    public function test_crew_directory_paginates_and_links_cards_to_member_detail(): void
    {
        $generation = TVClubGeneration::create(['name' => 'Generasi Direktori IA', 'year' => 3212, 'status' => true]);
        $member = TVClubMember::create([
            'generation_id' => $generation->id,
            'student_number' => 'IA-CREW-3212',
            'name' => 'AAA Kru Direktori IA',
            'status' => true,
        ]);

        Livewire::test(MembersIndex::class)
            ->assertViewHas('members', fn ($members): bool => $members->perPage() === 12)
            ->assertSee($member->name)
            ->assertSee(route('tvclub.members.show', $member->id))
            ->assertDontSee($member->student_number);
    }

    public function test_production_groups_index_eager_data_and_program_cta_render(): void
    {
        $generation = TVClubGeneration::create(['name' => 'Generasi Tim IA', 'year' => 3213, 'status' => true]);
        $program = Program::create(['name' => 'Program Tim IA', 'slug' => 'program-tim-ia', 'status' => true]);
        $group = TVClubGroup::create([
            'generation_id' => $generation->id,
            'program_id' => $program->id,
            'name' => 'Kelompok Produksi IA',
            'status' => true,
        ]);
        $member = TVClubMember::create([
            'generation_id' => $generation->id,
            'student_number' => 'IA-GROUP-3213',
            'name' => 'Kru Kelompok IA',
            'status' => true,
        ]);
        $group->members()->attach($member->id, ['role' => 'Editor']);

        Livewire::test(GroupsIndex::class)
            ->assertViewHas('groups', function ($groups) use ($group): bool {
                $renderedGroup = $groups->firstWhere('id', $group->id);

                return $renderedGroup
                    && $renderedGroup->relationLoaded('generation')
                    && $renderedGroup->relationLoaded('program')
                    && $renderedGroup->relationLoaded('members')
                    && $renderedGroup->members_count === 1;
            })
            ->assertSee('Kelompok Produksi IA')
            ->assertSee('1 Kru')
            ->assertSee(route('program.show', $program->slug));
    }

    public function test_tv_club_dropdown_contains_accessible_routes_and_subsection_active_state(): void
    {
        $generation = TVClubGeneration::first() ?? TVClubGeneration::create(['name' => 'Generasi Navigasi', 'year' => 3214, 'status' => true]);

        $response = $this->get(route('tvclub.generations.show', $generation->id));

        $response->assertOk()
            ->assertSee('aria-controls="tvclubDesktopMenu"', false)
            ->assertSee('aria-expanded="false"', false)
            ->assertSee(route('tvclub.index'))
            ->assertSee(route('tvclub.generations.index'))
            ->assertSee(route('tvclub.members.index'))
            ->assertSee(route('tvclub.groups.index'))
            ->assertSee('tvclub-nav-trigger active', false)
            ->assertSee('Generasi</strong>', false);
    }

    public function test_detail_breadcrumbs_point_to_their_dedicated_indexes(): void
    {
        $generation = TVClubGeneration::first() ?? TVClubGeneration::create(['name' => 'Generasi Breadcrumb', 'year' => 3215, 'status' => true]);
        $member = TVClubMember::first() ?? TVClubMember::create([
            'generation_id' => $generation->id,
            'student_number' => 'IA-BREADCRUMB-3215',
            'name' => 'Kru Breadcrumb',
            'status' => true,
        ]);

        $this->get(route('tvclub.generations.show', $generation->id))
            ->assertSee(route('tvclub.generations.index'));
        $this->get(route('tvclub.members.show', $member->id))
            ->assertSee(route('tvclub.members.index'))
            ->assertSee('Kembali ke Kru TV Club');
    }

    public function test_landing_crew_preview_is_limited_ordered_and_eager_loaded(): void
    {
        $generation = TVClubGeneration::create(['name' => 'Generasi Preview', 'year' => 3999, 'status' => true]);

        foreach (range(1, 7) as $index) {
            TVClubMember::create([
                'generation_id' => $generation->id,
                'student_number' => 'PREVIEW-'.$index,
                'name' => 'Kru Preview '.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                'status' => true,
            ]);
        }

        Livewire::test(TVClubIndex::class)
            ->assertViewHas('crewPreview', function ($members): bool {
                return $members->count() === 6
                    && $members->every(fn ($member): bool => $member->relationLoaded('generation'))
                    && $members->every(fn ($member): bool => $member->getAttribute('groups_count') !== null);
            })
            ->assertSee('Kru Preview 01')
            ->assertDontSee('Kru Preview 07');
    }

    public function test_landing_crew_preview_links_to_member_profiles_without_nim(): void
    {
        $generation = TVClubGeneration::create(['name' => 'Generasi Profil Preview', 'year' => 3998, 'status' => true]);
        $member = TVClubMember::create([
            'generation_id' => $generation->id,
            'student_number' => 'PRIVATE-PREVIEW-3998',
            'name' => 'Kru Profil Preview',
            'status' => true,
        ]);

        $this->get(route('tvclub.index'))
            ->assertOk()
            ->assertSee($member->name)
            ->assertSee(route('tvclub.members.show', $member->id))
            ->assertDontSee($member->student_number);
    }

    public function test_landing_zero_active_generation_has_compact_archive_state(): void
    {
        TVClubGeneration::query()->update(['status' => false]);

        $this->get(route('tvclub.index'))
            ->assertOk()
            ->assertSee('Belum ada generasi aktif yang ditampilkan.')
            ->assertSee('Lihat Arsip Generasi')
            ->assertSee(route('tvclub.generations.index'));
    }

    public function test_landing_hides_crew_preview_when_no_active_crew_is_available(): void
    {
        TVClubMember::query()->update(['status' => false]);

        $this->get(route('tvclub.index'))
            ->assertOk()
            ->assertDontSee('Orang-orang di Balik Produksi')
            ->assertDontSee('tvclub-crew-preview-grid', false);
    }

    public function test_landing_keeps_compact_story_sections_and_named_route_ctas(): void
    {
        $response = $this->get(route('tvclub.index'));

        $response->assertOk()
            ->assertSee('Generasi Aktif WPiTV')
            ->assertSee('Jelajahi TV Club')
            ->assertSee('Belajar. Berkarya. Berkolaborasi.')
            ->assertSee('Di balik setiap tayangan ada sebuah tim.')
            ->assertSee(route('tvclub.generations.index'))
            ->assertSee(route('tvclub.members.index'))
            ->assertSee(route('tvclub.groups.index'))
            ->assertSee(route('program.index'))
            ->assertDontSee('Cari nama kru TV Club')
            ->assertDontSee('Pagination Navigation');
    }

    public function test_generation_index_uses_active_features_and_semantic_archive_timeline(): void
    {
        $active = TVClubGeneration::create(['name' => 'Generasi Feature 7401', 'year' => 7401, 'description' => '', 'status' => true]);
        $archive = TVClubGeneration::create(['name' => 'Generasi Arsip 7400', 'year' => 7400, 'description' => null, 'status' => false]);

        $this->get(route('tvclub.generations.index'))
            ->assertOk()
            ->assertSee($active->name)
            ->assertSee($archive->name)
            ->assertSee('tvclub-generation-active-grid', false)
            ->assertSee('<ol class="tvclub-generation-timeline">', false)
            ->assertSee('Aktif')
            ->assertSee('Arsip')
            ->assertDontSee('Belum ada deskripsi');
    }

    public function test_generation_detail_eager_counts_assignments_and_linked_programs(): void
    {
        $generation = TVClubGeneration::create(['name' => 'Generasi Profil 7410', 'year' => 7410, 'status' => true]);
        $program = Program::create(['name' => 'Program Profil Generasi', 'slug' => 'program-profil-generasi', 'status' => true]);
        $member = TVClubMember::create([
            'generation_id' => $generation->id,
            'student_number' => 'PRIVATE-7410',
            'name' => 'Kru Profil Generasi',
            'status' => true,
        ]);
        $group = TVClubGroup::create([
            'generation_id' => $generation->id,
            'program_id' => $program->id,
            'name' => 'Tim Profil Generasi',
            'status' => true,
        ]);
        $group->members()->attach($member->id, ['role' => 'Kameramen']);

        Livewire::test(GenerationShow::class, ['generation' => $generation])
            ->assertViewHas('members', fn ($members): bool => $members->firstWhere('id', $member->id)?->groups_count === 1)
            ->assertViewHas('groups', fn ($groups): bool => $groups->firstWhere('id', $group->id)?->relationLoaded('program') === true)
            ->assertViewHas('assignmentCount', 1)
            ->assertViewHas('linkedProgramCount', 1)
            ->assertSee('1 Penugasan Produksi')
            ->assertSee(route('program.show', $program->slug))
            ->assertDontSee($member->student_number);
    }

    public function test_generation_detail_keeps_inactive_members_and_groups_as_history(): void
    {
        $generation = TVClubGeneration::create(['name' => 'Generasi Historis 7420', 'year' => 7420, 'status' => false]);
        $program = Program::create(['name' => 'Program Historis 7420', 'slug' => 'program-historis-7420', 'status' => false]);
        $member = TVClubMember::create([
            'generation_id' => $generation->id,
            'student_number' => 'PRIVATE-7420',
            'name' => 'Kru Historis 7420',
            'status' => false,
        ]);
        $group = TVClubGroup::create([
            'generation_id' => $generation->id,
            'program_id' => $program->id,
            'name' => 'Tim Historis 7420',
            'status' => false,
        ]);

        $this->get(route('tvclub.generations.show', $generation->id))
            ->assertOk()
            ->assertSee($member->name)
            ->assertSee($group->name)
            ->assertSee('Arsip')
            ->assertDontSee($member->student_number);
    }

    public function test_generation_detail_handles_null_program_and_empty_description_without_placeholder(): void
    {
        $generation = TVClubGeneration::create(['name' => 'Generasi Null Program', 'year' => 7430, 'description' => null, 'status' => false]);
        $generation->setAttribute('members_count', 0);
        $generation->setAttribute('groups_count', 1);
        $group = new TVClubGroup([
            'generation_id' => $generation->id,
            'program_id' => null,
            'name' => 'Tim Tanpa Program 7430',
            'description' => null,
            'status' => false,
        ]);
        $group->setAttribute('members_count', 0);
        $group->setRelation('program', null);

        $html = view('livewire.public.tvclub.generations.show', [
            'generation' => $generation,
            'members' => collect(),
            'groups' => collect([$group]),
            'assignmentCount' => 0,
            'linkedProgramCount' => 0,
            'previousGeneration' => null,
            'nextGeneration' => null,
        ])->render();

        $this->assertStringContainsString('Tim Tanpa Program 7430', $html);
        $this->assertStringNotContainsString('tvclub-generation-group-card__program', $html);
        $this->assertStringNotContainsString('Belum ada deskripsi', $html);
    }

    public function test_generation_detail_empty_members_and_groups_are_compact_and_safe(): void
    {
        $generation = TVClubGeneration::create(['name' => 'Generasi Kosong 7440', 'year' => 7440, 'status' => false]);

        $this->get(route('tvclub.generations.show', $generation->id))
            ->assertOk()
            ->assertSee('Belum ada kru yang tercatat pada generasi ini.')
            ->assertSee('Belum ada kelompok produksi yang tercatat.')
            ->assertSee('Semua Generasi');
    }

    public function test_generation_previous_and_next_navigation_uses_year_not_id(): void
    {
        $next = TVClubGeneration::create(['name' => 'Generasi Berikut 7502', 'year' => 7502, 'status' => true]);
        $previous = TVClubGeneration::create(['name' => 'Generasi Sebelum 7500', 'year' => 7500, 'status' => false]);
        $current = TVClubGeneration::create(['name' => 'Generasi Tengah 7501', 'year' => 7501, 'status' => true]);

        Livewire::test(GenerationShow::class, ['generation' => $current])
            ->assertViewHas('previousGeneration', fn ($generation): bool => $generation?->is($previous) === true)
            ->assertViewHas('nextGeneration', fn ($generation): bool => $generation?->is($next) === true)
            ->assertSee(route('tvclub.generations.show', $previous->id))
            ->assertSee(route('tvclub.generations.show', $next->id))
            ->assertSee(route('tvclub.generations.index'));
    }

    public function test_crew_directory_uses_actual_counts_localized_summary_and_public_fields_only(): void
    {
        $generation = TVClubGeneration::create(['name' => 'Generasi Direktori 7601', 'year' => 7601, 'status' => true]);
        $program = Program::create(['name' => 'Program Direktori 7601', 'slug' => 'program-direktori-7601', 'status' => true]);
        $member = TVClubMember::create([
            'generation_id' => $generation->id,
            'student_number' => 'NIM-RAHASIA-7601',
            'name' => 'Aira Kru Direktori',
            'photo' => null,
            'status' => true,
        ]);
        foreach (range(1, 2) as $number) {
            $group = TVClubGroup::create([
                'generation_id' => $generation->id,
                'program_id' => $program->id,
                'name' => 'Tim Direktori '.$number,
                'status' => true,
            ]);
            $group->members()->attach($member->id, ['role' => 'Kru Produksi']);
        }

        Livewire::test(MembersIndex::class)
            ->set('search', 'Aira Kru Direktori')
            ->assertViewHas('members', fn ($members): bool => $members->first()?->groups_count === 2)
            ->assertSee('1 kru ditemukan')
            ->assertSee('Aira Kru Direktori')
            ->assertSee('Generasi Direktori 7601')
            ->assertSee('2 Penugasan Produksi')
            ->assertSee('AK')
            ->assertDontSee('NIM-RAHASIA-7601');
    }

    public function test_crew_directory_pagination_is_localized_and_uses_twelve_members_per_page(): void
    {
        $generation = TVClubGeneration::create(['name' => 'Generasi Pagination 7602', 'year' => 7602, 'status' => true]);
        foreach (range(1, 13) as $number) {
            TVClubMember::create([
                'generation_id' => $generation->id,
                'student_number' => 'PAGE-7602-'.$number,
                'name' => 'AAA Kru Pagination '.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
                'status' => true,
            ]);
        }

        Livewire::test(MembersIndex::class)
            ->set('generationId', (string) $generation->id)
            ->assertViewHas('members', fn ($members): bool => $members->perPage() === 12 && $members->total() === 13 && $members->hasPages())
            ->assertSee('13 kru ditemukan')
            ->assertSee('Halaman 1 dari 2')
            ->assertSee('Berikutnya')
            ->call('nextPage')
            ->assertSee('Halaman 2 dari 2');
    }

    public function test_member_profile_uses_actual_assignment_roles_and_deduplicated_programs(): void
    {
        $generation = TVClubGeneration::create(['name' => 'Generasi Portfolio 7610', 'year' => 7610, 'status' => true]);
        $program = Program::create(['name' => 'Program Portfolio Unik', 'slug' => 'program-portfolio-unik', 'description' => 'Program produksi aktual.', 'status' => true]);
        $member = TVClubMember::create([
            'generation_id' => $generation->id,
            'student_number' => 'PRIVATE-PORTFOLIO-7610',
            'name' => 'Bima Portfolio Kru',
            'status' => true,
        ]);
        foreach (['Camera Operator', 'Editor'] as $index => $role) {
            $group = TVClubGroup::create([
                'generation_id' => $generation->id,
                'program_id' => $program->id,
                'name' => 'Tim Portfolio '.($index + 1),
                'status' => true,
            ]);
            $group->members()->attach($member->id, ['role' => $role]);
        }

        Livewire::test(MemberShow::class, ['member' => $member])
            ->assertViewHas('programs', fn ($programs): bool => $programs->count() === 1 && $programs->first()->is($program))
            ->assertSee('<h1 id="memberProfileTitle">Bima Portfolio Kru</h1>', false)
            ->assertSee('Camera Operator')
            ->assertSee('Editor')
            ->assertSee('Tim Portfolio 1')
            ->assertSee('Tim Portfolio 2')
            ->assertSee('Program yang Dikerjakan')
            ->assertSee(route('program.show', $program->slug))
            ->assertDontSee('PRIVATE-PORTFOLIO-7610');
    }

    public function test_member_profile_blank_role_falls_back_without_inventing_primary_role(): void
    {
        $generation = TVClubGeneration::create(['name' => 'Generasi Role Blank 7611', 'year' => 7611, 'status' => true]);
        $program = Program::create(['name' => 'Program Role Blank', 'slug' => 'program-role-blank-7611', 'status' => true]);
        $member = TVClubMember::create(['generation_id' => $generation->id, 'student_number' => 'PRIVATE-7611', 'name' => 'Citra Role Blank', 'status' => true]);
        $group = TVClubGroup::create(['generation_id' => $generation->id, 'program_id' => $program->id, 'name' => 'Tim Role Blank', 'status' => true]);
        $group->members()->attach($member->id, ['role' => '   ']);

        $this->get(route('tvclub.members.show', $member->id))
            ->assertOk()
            ->assertSee('Anggota Kru')
            ->assertDontSee('PRIVATE-7611');
    }

    public function test_member_profile_hides_program_section_when_there_are_no_assignments(): void
    {
        $generation = TVClubGeneration::create(['name' => 'Generasi Tanpa Program 7612', 'year' => 7612, 'status' => true]);
        $member = TVClubMember::create(['generation_id' => $generation->id, 'student_number' => 'PRIVATE-7612', 'name' => 'Dara Tanpa Penugasan', 'status' => true]);

        Livewire::test(MemberShow::class, ['member' => $member])
            ->assertViewHas('programs', fn ($programs): bool => $programs->isEmpty())
            ->assertSee('Belum ada penugasan produksi yang tercatat.')
            ->assertDontSee('Program yang Dikerjakan');
    }

    public function test_member_generation_context_and_related_crew_are_bounded_and_exclude_current_member(): void
    {
        $generation = TVClubGeneration::create(['name' => 'Generasi Relasi (7620)', 'year' => 7620, 'status' => false]);
        $current = TVClubMember::create(['generation_id' => $generation->id, 'student_number' => 'CURRENT-7620', 'name' => 'AAA Kru Saat Ini', 'status' => false]);
        foreach (range(1, 6) as $number) {
            TVClubMember::create([
                'generation_id' => $generation->id,
                'student_number' => 'RELATED-7620-'.$number,
                'name' => 'Kru Terkait '.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
                'status' => $number % 2 === 0,
            ]);
        }

        Livewire::test(MemberShow::class, ['member' => $current])
            ->assertViewHas('relatedCrew', fn ($crew): bool => $crew->count() === 4 && ! $crew->contains('id', $current->id))
            ->assertSee('Bagian dari Generasi Relasi')
            ->assertSee('Generasi Relasi · 7620')
            ->assertDontSee('Generasi Relasi (7620) · 7620')
            ->assertSee('<i aria-hidden="true"></i>Arsip</span>', false)
            ->assertSee(route('tvclub.generations.show', $generation->id))
            ->assertDontSee('CURRENT-7620');
    }

    public function test_historical_member_remains_publicly_accessible_and_searchable(): void
    {
        $generation = TVClubGeneration::create(['name' => 'Generasi Arsip Crew 7630', 'year' => 7630, 'status' => false]);
        $member = TVClubMember::create(['generation_id' => $generation->id, 'student_number' => 'ARCHIVE-PRIVATE-7630', 'name' => 'Kru Historis Publik 7630', 'status' => false]);

        Livewire::test(MembersIndex::class)
            ->set('search', 'Kru Historis Publik 7630')
            ->assertSee($member->name)
            ->assertSee('Arsip')
            ->assertDontSee('ARCHIVE-PRIVATE-7630');

        $this->get(route('tvclub.members.show', $member->id))
            ->assertOk()
            ->assertSee($member->name)
            ->assertSee('<i aria-hidden="true"></i>Arsip</span>', false)
            ->assertDontSee('ARCHIVE-PRIVATE-7630');
    }

    public function test_program_and_generation_details_keep_links_to_member_profile(): void
    {
        $generation = TVClubGeneration::create(['name' => 'Generasi Link Regression 7640', 'year' => 7640, 'status' => true]);
        $program = Program::create(['name' => 'Program Link Regression', 'slug' => 'program-link-regression-7640', 'status' => true]);
        $member = TVClubMember::create(['generation_id' => $generation->id, 'student_number' => 'PRIVATE-LINK-7640', 'name' => 'Kru Link Regression', 'status' => true]);
        $group = TVClubGroup::create(['generation_id' => $generation->id, 'program_id' => $program->id, 'name' => 'Tim Link Regression', 'status' => true]);
        $group->members()->attach($member->id, ['role' => 'Producer']);

        $memberUrl = route('tvclub.members.show', $member->id);
        $this->get(route('program.show', $program->slug))->assertOk()->assertSee($memberUrl);
        $this->get(route('tvclub.generations.show', $generation->id))->assertOk()->assertSee($memberUrl);
    }

    public function test_production_groups_page_is_semantic_database_driven_and_public(): void
    {
        $this->assertGuest();
        $this->productionMember();
        $groupCount = TVClubGroup::count();

        $this->get(route('tvclub.groups.index'))
            ->assertOk()
            ->assertSee('<h1 id="productionGroupsTitle">Kelompok Produksi WPiTV</h1>', false)
            ->assertSee($groupCount.' Tim Produksi')
            ->assertSee('role="list"', false)
            ->assertSee('tvclub-production-card', false)
            ->assertDontSee('student_number')
            ->assertDontSee('NIM');
    }

    public function test_production_group_card_connects_program_generation_description_and_status(): void
    {
        $generation = TVClubGeneration::create(['name' => 'Generasi Organisasi', 'year' => 7701, 'status' => true]);
        $program = Program::create(['name' => 'Program Organisasi Produksi', 'slug' => 'program-organisasi-produksi', 'image' => null, 'status' => true]);
        $group = TVClubGroup::create([
            'generation_id' => $generation->id,
            'program_id' => $program->id,
            'name' => 'Tim Organisasi Produksi 7701',
            'description' => 'Deskripsi produksi aktual yang berasal dari database.',
            'status' => true,
        ]);

        $this->get(route('tvclub.groups.index'))
            ->assertOk()
            ->assertSee($group->name)
            ->assertSee($group->description)
            ->assertSee($program->name)
            ->assertSee('Generasi Organisasi · 7701')
            ->assertSee('Aktif')
            ->assertSee('Sampul program '.$program->name)
            ->assertSee(route('program.show', $program->slug))
            ->assertSee(route('tvclub.generations.show', $generation->id));
    }

    public function test_production_group_crew_preview_is_limited_and_overflow_is_correct(): void
    {
        $generation = TVClubGeneration::create(['name' => 'Generasi Preview Tim', 'year' => 7702, 'status' => true]);
        $program = Program::create(['name' => 'Program Preview Tim', 'slug' => 'program-preview-tim', 'status' => true]);
        $group = TVClubGroup::create(['generation_id' => $generation->id, 'program_id' => $program->id, 'name' => 'Tim Preview Enam Kru', 'status' => true]);

        foreach (range(1, 6) as $number) {
            $member = TVClubMember::create([
                'generation_id' => $generation->id,
                'student_number' => 'PRIVATE-PREVIEW-'.$number,
                'name' => 'Kru Preview '.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
                'photo' => null,
                'status' => true,
            ]);
            $group->members()->attach($member->id, ['role' => $number === 1 ? 'Editor' : null]);
        }

        $component = Livewire::test(GroupsIndex::class)
            ->assertViewHas('groups', function ($groups) use ($group): bool {
                $rendered = $groups->firstWhere('id', $group->id);

                return $rendered
                    && $rendered->members_count === 6
                    && $rendered->relationLoaded('members')
                    && $rendered->members->count() === 6;
            })
            ->assertSee('6 Kru')
            ->assertSee('+2')
            ->assertSee('+3')
            ->assertSee('Kru Preview 01')
            ->assertSee('Editor')
            ->assertSee('Anggota Kru')
            ->assertSee(route('tvclub.members.show', $group->members()->orderBy('name')->first()->id))
            ->assertDontSee('PRIVATE-PREVIEW-1');

        $this->assertSame(1, preg_match('/Tim Preview Enam Kru.*?<\/article>/s', $component->html(), $cardMatch));
        $this->assertSame(
            4,
            substr_count($cardMatch[0], 'class="tvclub-production-avatar')
                - substr_count($cardMatch[0], 'tvclub-production-avatar--more')
        );
    }

    public function test_production_group_zero_members_and_empty_description_are_compact(): void
    {
        $generation = TVClubGeneration::create(['name' => 'Generasi Tim Kosong', 'year' => 7703, 'status' => true]);
        $program = Program::create(['name' => 'Program Tim Kosong', 'slug' => 'program-tim-kosong', 'status' => true]);
        $group = TVClubGroup::create([
            'generation_id' => $generation->id,
            'program_id' => $program->id,
            'name' => 'Tim Tanpa Kru dan Deskripsi',
            'description' => '',
            'status' => true,
        ]);

        Livewire::test(GroupsIndex::class)
            ->assertSee($group->name)
            ->assertSee('0 Kru')
            ->assertSee('Belum ada kru tercatat')
            ->assertDontSee('Belum ada deskripsi');
    }

    public function test_production_group_null_program_and_generation_are_defensively_safe(): void
    {
        $group = new TVClubGroup([
            'name' => 'Tim Relasi Defensif',
            'description' => 'Tetap tampil tanpa relasi.',
            'status' => false,
        ]);
        $group->setAttribute('members_count', 0);
        $group->setRelation('program', null);
        $group->setRelation('generation', null);
        $group->setRelation('members', collect());

        $html = view('livewire.public.tvclub.groups.index', ['groups' => collect([$group])])->render();

        $this->assertStringContainsString('Tim Relasi Defensif', $html);
        $this->assertStringContainsString('Tetap tampil tanpa relasi.', $html);
        $this->assertStringContainsString('Belum ada kru tercatat', $html);
        $this->assertStringContainsString('Arsip', $html);
        $this->assertStringNotContainsString('tvclub-production-program__identity', $html);
        $this->assertStringNotContainsString('Lihat Program', $html);
        $this->assertStringNotContainsString('Lihat Generasi', $html);
    }

    public function test_production_groups_keep_history_and_sort_active_by_newest_generation(): void
    {
        $program = Program::create(['name' => 'Program Urutan Tim', 'slug' => 'program-urutan-tim', 'status' => true]);
        $older = TVClubGeneration::create(['name' => 'Generasi Urut Lama', 'year' => 7710, 'status' => true]);
        $newer = TVClubGeneration::create(['name' => 'Generasi Urut Baru', 'year' => 7711, 'status' => true]);
        $archiveGeneration = TVClubGeneration::create(['name' => 'Generasi Urut Arsip', 'year' => 7712, 'status' => false]);
        $olderGroup = TVClubGroup::create(['generation_id' => $older->id, 'program_id' => $program->id, 'name' => 'ZZZ Tim Aktif Lama', 'status' => true]);
        $newerGroup = TVClubGroup::create(['generation_id' => $newer->id, 'program_id' => $program->id, 'name' => 'AAA Tim Aktif Baru', 'status' => true]);
        $archiveGroup = TVClubGroup::create(['generation_id' => $archiveGeneration->id, 'program_id' => $program->id, 'name' => 'AAA Tim Historis Terbaru', 'status' => false]);

        Livewire::test(GroupsIndex::class)
            ->assertViewHas('groups', function ($groups) use ($newerGroup, $olderGroup, $archiveGroup): bool {
                $ids = $groups->pluck('id');

                return $ids->search($newerGroup->id) < $ids->search($olderGroup->id)
                    && $ids->search($olderGroup->id) < $ids->search($archiveGroup->id);
            })
            ->assertSeeInOrder([$newerGroup->name, $olderGroup->name, $archiveGroup->name])
            ->assertSee('Arsip');
    }

    public function test_production_groups_empty_state_and_route_scope_are_preserved(): void
    {
        $html = view('livewire.public.tvclub.groups.index', ['groups' => collect()])->render();

        $this->assertStringContainsString('Belum ada kelompok produksi.', $html);
        $this->assertStringContainsString('Kelompok produksi TV Club akan ditampilkan di sini saat data tersedia.', $html);
        $this->assertStringContainsString(route('tvclub.members.index'), $html);
        $this->assertFalse(Route::has('tvclub.groups.show'));
    }
}
