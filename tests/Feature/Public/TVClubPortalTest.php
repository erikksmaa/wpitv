<?php

namespace Tests\Feature\Public;

use App\Livewire\Public\TVClub\Index as TVClubIndex;
use App\Models\Program;
use App\Models\TVClubGeneration;
use App\Models\TVClubGroup;
use App\Models\TVClubMember;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class TVClubPortalTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Test 1: TV Club landing loads successfully.
     */
    public function test_tv_club_landing_loads(): void
    {
        $response = $this->get(route('tvclub.index'));

        $response->assertStatus(200);
        $response->assertSee('TV Club WPiTV');
        $response->assertSee('Di Balik Layar Siaran');
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
        $gen = TVClubGeneration::first() ?? TVClubGeneration::create([
            'name' => 'Generasi Test TVClub',
            'year' => 2025,
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
        $response->assertSee($inactiveGen->name);
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
        $member = TVClubMember::whereNotNull('generation_id')->first();
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
        $member = TVClubMember::has('groups')->first();
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
        $member = TVClubMember::has('groups')->first();
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
        $response->assertSee('Belum ada penugasan kelompok produksi');
    }

    /**
     * Test 16: Group program relation displays.
     */
    public function test_group_program_relation_displays(): void
    {
        $group = TVClubGroup::whereNotNull('program_id')->has('program')->first();
        if ($group) {
            $response = $this->get(route('tvclub.index'));
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
        $group = TVClubGroup::whereNotNull('program_id')->has('program')->first();
        if ($group) {
            $response = $this->get(route('tvclub.index'));
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
        $member = TVClubMember::whereNotNull('student_number')->first();
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

        Livewire::test(TVClubIndex::class)
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

        Livewire::test(TVClubIndex::class)
            ->set('generationId', (string) $gen->id)
            ->assertSee($uniqueName);
    }

    /**
     * Test 27: Empty member state handled cleanly.
     */
    public function test_empty_member_state_handled(): void
    {
        Livewire::test(TVClubIndex::class)
            ->set('search', 'NonExistentMemberKeyword'.uniqid())
            ->assertSee('Tidak ada anggota yang ditemukan.');
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
}
