<?php

namespace Tests\Feature\Admin;

use App\Models\Banner;
use App\Models\News;
use App\Models\Program;
use App\Models\Role;
use App\Models\TVClubGeneration;
use App\Models\TVClubGroup;
use App\Models\TVClubMember;
use App\Models\User;
use App\Models\UserCounter;
use App\Models\Video;
use Database\Seeders\TVClubSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TVClubSeederTest extends TestCase
{
    use DatabaseTransactions;

    public function test_seeder_creates_generations_members_groups_and_assignments(): void
    {
        $this->seed(TVClubSeeder::class);

        $this->assertGreaterThanOrEqual(4, TVClubGeneration::count());
        $this->assertGreaterThanOrEqual(20, TVClubMember::count());
        $this->assertGreaterThanOrEqual(6, TVClubGroup::count());
        $this->assertGreaterThanOrEqual(20, DB::table('tvclub_group_members')->count());
    }

    public function test_all_groups_have_valid_programs_and_generations(): void
    {
        $this->seed(TVClubSeeder::class);

        $groups = TVClubGroup::with(['generation', 'program'])->get();

        foreach ($groups as $group) {
            $this->assertNotNull($group->generation, "Group {$group->name} must have a valid generation.");
            $this->assertNotNull($group->program, "Group {$group->name} must have a valid program.");
        }
    }

    public function test_all_members_have_valid_generations(): void
    {
        $this->seed(TVClubSeeder::class);

        $members = TVClubMember::with('generation')->get();

        foreach ($members as $member) {
            $this->assertNotNull($member->generation, "Member {$member->name} must have a valid generation.");
        }
    }

    public function test_all_assignments_belong_to_same_generation(): void
    {
        $this->seed(TVClubSeeder::class);

        $groups = TVClubGroup::with(['members'])->get();

        foreach ($groups as $group) {
            foreach ($group->members as $member) {
                $this->assertEquals(
                    $group->generation_id,
                    $member->generation_id,
                    "Member {$member->name} generation ({$member->generation_id}) must match Group {$group->name} generation ({$group->generation_id})."
                );
                $this->assertNotEmpty($member->pivot->role, "Pivot role for {$member->name} in {$group->name} cannot be empty.");
            }
        }
    }

    public function test_no_duplicate_assignment_per_group(): void
    {
        $this->seed(TVClubSeeder::class);

        $pivotRows = DB::table('tvclub_group_members')->get();
        $pairs = [];

        foreach ($pivotRows as $row) {
            $key = $row->group_id.'-'.$row->member_id;
            $this->assertNotContains($key, $pairs, "Duplicate pivot assignment found for group {$row->group_id} and member {$row->member_id}.");
            $pairs[] = $key;
        }
    }

    public function test_seeder_is_fully_idempotent(): void
    {
        // Run once
        $this->seed(TVClubSeeder::class);
        $genCount1 = TVClubGeneration::count();
        $memCount1 = TVClubMember::count();
        $grpCount1 = TVClubGroup::count();
        $pivCount1 = DB::table('tvclub_group_members')->count();

        // Run second time
        $this->seed(TVClubSeeder::class);
        $genCount2 = TVClubGeneration::count();
        $memCount2 = TVClubMember::count();
        $grpCount2 = TVClubGroup::count();
        $pivCount2 = DB::table('tvclub_group_members')->count();

        $this->assertEquals($genCount1, $genCount2, 'Generations count must remain unchanged after re-seeding.');
        $this->assertEquals($memCount1, $memCount2, 'Members count must remain unchanged after re-seeding.');
        $this->assertEquals($grpCount1, $grpCount2, 'Groups count must remain unchanged after re-seeding.');
        $this->assertEquals($pivCount1, $pivCount2, 'Pivot count must remain unchanged after re-seeding.');
    }

    public function test_existing_legacy_modules_data_remains_intact(): void
    {
        $this->assertEquals(21, Program::count());
        $this->assertEquals(409, Video::count());
        $this->assertEquals(367, News::count());
        $this->assertEquals(2, Banner::count());
        $this->assertGreaterThanOrEqual(35487, UserCounter::count());
        $this->assertEquals(3, Role::count());
        $this->assertEquals(3, User::count());
    }
}
