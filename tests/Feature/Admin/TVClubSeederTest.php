<?php

namespace Tests\Feature\Admin;

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

    protected function setUp(): void
    {
        parent::setUp();
        // Repository demo seeder expects these program prerequisites; never load application rows.
        foreach (['NGOPOT', 'PODCAST', 'TODAY', 'AKTUAL', 'INSIGHT', 'HEALING', 'HARMONI'] as $name) {
            Program::create(['name' => $name, 'slug' => strtolower($name), 'status' => 1]);
        }
    }

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
        $author = $this->contentAuthor();
        Video::create(['title' => 'Video Uji Lama', 'user_id' => $author->id, 'youtube_id' => 'testVideo01', 'air_date' => '2020-01-01']);
        News::create(['title' => 'Berita Uji Lama', 'slug' => 'berita-uji-lama', 'content' => 'Konten uji', 'user_id' => $author->id]);
        // Historical table is retained, but has no application model or CRUD.
        DB::table('ms_banner')->insert(['title' => 'Banner Uji Lama']);
        $legacyBanners = DB::table('ms_banner')->orderBy('id')->get()->toArray();
        UserCounter::create(['ip' => '192.0.2.1', 'tanggal' => '2020-01-01', 'hits' => 1, 'online' => 1]);
        $models = [Program::class, Video::class, News::class, UserCounter::class, Role::class, User::class];
        $before = [];
        foreach ($models as $model) {
            $before[$model] = $model::orderBy('id')->get()->map->getAttributes()->all();
        }
        $this->seed(TVClubSeeder::class);
        foreach ($models as $model) {
            $this->assertSame($before[$model], $model::orderBy('id')->get()->map->getAttributes()->all(), $model.' must remain unchanged');
        }
        $this->assertEquals($legacyBanners, DB::table('ms_banner')->orderBy('id')->get()->toArray());
    }
}
