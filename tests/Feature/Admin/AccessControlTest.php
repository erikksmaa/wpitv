<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\TVClub\Generations\Form as GenerationForm;
use App\Livewire\Admin\TVClub\Groups\Form as GroupForm;
use App\Livewire\Admin\TVClub\Members\Form as MemberForm;
use App\Models\Program;
use App\Models\Role;
use App\Models\TVClubGeneration;
use App\Models\TVClubGroup;
use App\Models\TVClubMember;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use DatabaseTransactions;

    protected User $superAdmin;

    protected User $adminProduction;

    protected User $adminNews;

    protected User $inactiveUser;

    protected TVClubGeneration $generation;

    protected Program $program;

    protected function setUp(): void
    {
        parent::setUp();

        $superAdminRole = Role::where('name', 'Super Admin')->firstOrFail();
        $prodRole = Role::where('name', 'Admin Production')->firstOrFail();
        $newsRole = Role::where('name', 'Admin News')->firstOrFail();

        $this->superAdmin = User::factory()->create([
            'name' => 'Super Admin Test',
            'role_id' => $superAdminRole->id,
            'status' => 1,
        ]);

        $this->adminProduction = User::factory()->create([
            'name' => 'Admin Production Test',
            'role_id' => $prodRole->id,
            'status' => 1,
        ]);

        $this->adminNews = User::factory()->create([
            'name' => 'Admin News Test',
            'role_id' => $newsRole->id,
            'status' => 1,
        ]);

        $this->inactiveUser = User::factory()->create([
            'name' => 'Inactive User Test',
            'role_id' => $superAdminRole->id,
            'status' => 0,
        ]);

        $this->generation = TVClubGeneration::where('year', 2025)->first() ?? TVClubGeneration::create([
            'name' => 'Generasi Test 2025',
            'year' => 2085,
            'status' => 1,
        ]);

        $this->program = Program::first() ?? Program::create([
            'name' => 'Program Test',
            'slug' => 'program-test',
            'status' => 1,
        ]);
    }

    public function test_guest_cannot_access_admin_dashboard_or_modules(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->get(route('admin.tvclub.generations.index'))->assertRedirect(route('login'));
        $this->get(route('admin.tvclub.members.index'))->assertRedirect(route('login'));
        $this->get(route('admin.tvclub.groups.index'))->assertRedirect(route('login'));
    }

    public function test_inactive_user_cannot_access_admin_and_is_logged_out(): void
    {
        $response = $this->actingAs($this->inactiveUser)->get(route('admin.dashboard'));
        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_super_admin_can_access_all_admin_modules(): void
    {
        $this->actingAs($this->superAdmin)->get(route('admin.dashboard'))->assertStatus(200);
        $this->actingAs($this->superAdmin)->get(route('admin.programs.index'))->assertStatus(200);
        $this->actingAs($this->superAdmin)->get(route('admin.videos.index'))->assertStatus(200);
        $this->actingAs($this->superAdmin)->get(route('admin.news.index'))->assertStatus(200);
        $this->actingAs($this->superAdmin)->get(route('admin.visitor-statistics.index'))->assertStatus(200);
        $this->actingAs($this->superAdmin)->get(route('admin.tvclub.generations.index'))->assertStatus(200);
        $this->actingAs($this->superAdmin)->get(route('admin.tvclub.members.index'))->assertStatus(200);
        $this->actingAs($this->superAdmin)->get(route('admin.tvclub.groups.index'))->assertStatus(200);
    }

    public function test_admin_production_can_access_permitted_modules(): void
    {
        $this->actingAs($this->adminProduction)->get(route('admin.dashboard'))->assertStatus(200);
        $this->actingAs($this->adminProduction)->get(route('admin.programs.index'))->assertStatus(200);
        $this->actingAs($this->adminProduction)->get(route('admin.videos.index'))->assertStatus(200);
        $this->actingAs($this->adminProduction)->get(route('admin.tvclub.generations.index'))->assertStatus(200);
        $this->actingAs($this->adminProduction)->get(route('admin.tvclub.members.index'))->assertStatus(200);
        $this->actingAs($this->adminProduction)->get(route('admin.tvclub.groups.index'))->assertStatus(200);
    }

    public function test_admin_news_cannot_view_tv_club_index_pages(): void
    {
        $this->actingAs($this->adminNews)->get(route('admin.tvclub.generations.index'))->assertForbidden();
        $this->actingAs($this->adminNews)->get(route('admin.tvclub.members.index'))->assertForbidden();
        $this->actingAs($this->adminNews)->get(route('admin.tvclub.groups.index'))->assertForbidden();
    }

    public function test_admin_news_cannot_access_create_generation_direct_url(): void
    {
        $this->actingAs($this->adminNews)->get(route('admin.tvclub.generations.create'))->assertStatus(403);
    }

    public function test_admin_news_cannot_access_edit_generation_direct_url(): void
    {
        $this->actingAs($this->adminNews)->get(route('admin.tvclub.generations.edit', $this->generation))->assertStatus(403);
    }

    public function test_admin_news_cannot_delete_generation_via_livewire(): void
    {
        $this->actingAs($this->adminNews)->get(route('admin.tvclub.generations.index'))->assertForbidden();
        $this->assertDatabaseHas('tvclub_generations', ['id' => $this->generation->id]);
    }

    public function test_admin_news_cannot_access_create_member_direct_url(): void
    {
        $this->actingAs($this->adminNews)->get(route('admin.tvclub.members.create'))->assertStatus(403);
    }

    public function test_admin_news_cannot_access_edit_member_direct_url(): void
    {
        $member = TVClubMember::create([
            'generation_id' => $this->generation->id,
            'student_number' => '21.240.7001',
            'name' => 'Member Test News Edit',
            'status' => 1,
        ]);

        $this->actingAs($this->adminNews)->get(route('admin.tvclub.members.edit', $member))->assertStatus(403);
    }

    public function test_admin_news_cannot_delete_member_via_livewire(): void
    {
        $member = TVClubMember::create([
            'generation_id' => $this->generation->id,
            'student_number' => '21.240.7002',
            'name' => 'Member Test News Delete',
            'status' => 1,
        ]);

        $this->actingAs($this->adminNews)->get(route('admin.tvclub.members.index'))->assertForbidden();
        $this->assertDatabaseHas('tvclub_members', ['id' => $member->id]);
    }

    public function test_admin_news_cannot_access_create_group_direct_url(): void
    {
        $this->actingAs($this->adminNews)->get(route('admin.tvclub.groups.create'))->assertStatus(403);
    }

    public function test_admin_news_cannot_access_edit_group_direct_url(): void
    {
        $group = TVClubGroup::create([
            'generation_id' => $this->generation->id,
            'program_id' => $this->program->id,
            'name' => 'Group Test News Edit',
            'status' => 1,
        ]);

        $this->actingAs($this->adminNews)->get(route('admin.tvclub.groups.edit', $group))->assertStatus(403);
    }

    public function test_admin_news_cannot_delete_group_via_livewire(): void
    {
        $group = TVClubGroup::create([
            'generation_id' => $this->generation->id,
            'program_id' => $this->program->id,
            'name' => 'Group Test News Delete',
            'status' => 1,
        ]);

        $this->actingAs($this->adminNews)->get(route('admin.tvclub.groups.index'))->assertForbidden();
        $this->assertDatabaseHas('tvclub_groups', ['id' => $group->id]);
    }

    public function test_admin_news_cannot_access_create_or_edit_forms_to_assign_members(): void
    {
        $group = TVClubGroup::create([
            'generation_id' => $this->generation->id,
            'program_id' => $this->program->id,
            'name' => 'Group Test News Direct',
            'status' => 1,
        ]);

        $this->actingAs($this->adminNews)->get(route('admin.tvclub.groups.create'))->assertStatus(403);
        $this->actingAs($this->adminNews)->get(route('admin.tvclub.groups.edit', $group))->assertStatus(403);
    }

    public function test_unauthorized_direct_urls_return_403(): void
    {
        $this->actingAs($this->adminNews)->get(route('admin.tvclub.generations.create'))->assertStatus(403);
        $this->actingAs($this->adminNews)->get(route('admin.tvclub.generations.edit', $this->generation))->assertStatus(403);
        $this->actingAs($this->adminNews)->get(route('admin.tvclub.members.create'))->assertStatus(403);
        $this->actingAs($this->adminNews)->get(route('admin.tvclub.groups.create'))->assertStatus(403);
        $this->actingAs($this->adminProduction)->get(route('admin.news.create'))->assertStatus(403);
    }

    public function test_unauthorized_module_access_cannot_delete_records(): void
    {
        $member = TVClubMember::create([
            'generation_id' => $this->generation->id,
            'student_number' => '21.240.7010',
            'name' => 'Member Test Hack Delete',
            'status' => 1,
        ]);

        $group = TVClubGroup::create([
            'generation_id' => $this->generation->id,
            'program_id' => $this->program->id,
            'name' => 'Group Test Hack Delete',
            'status' => 1,
        ]);

        $this->actingAs($this->adminNews)->get(route('admin.tvclub.generations.index'))->assertForbidden();
        $this->actingAs($this->adminNews)->get(route('admin.tvclub.members.index'))->assertForbidden();
        $this->actingAs($this->adminNews)->get(route('admin.tvclub.groups.index'))->assertForbidden();
        $this->assertDatabaseHas('tvclub_generations', ['id' => $this->generation->id]);
        $this->assertDatabaseHas('tvclub_members', ['id' => $member->id]);
        $this->assertDatabaseHas('tvclub_groups', ['id' => $group->id]);
    }

    public function test_authorized_user_can_perform_permitted_mutations(): void
    {
        // Admin Production can create Generation
        Livewire::actingAs($this->adminProduction)
            ->test(GenerationForm::class)
            ->set('name', 'Generasi Prod 2094')
            ->set('year', 2094)
            ->set('status', true)
            ->call('save')
            ->assertRedirect(route('admin.tvclub.generations.index'));

        $this->assertDatabaseHas('tvclub_generations', ['year' => 2094]);

        // Admin Production can create Member
        Livewire::actingAs($this->adminProduction)
            ->test(MemberForm::class)
            ->set('name', 'Member Prod Test')
            ->set('student_number', '21.240.7005')
            ->set('generation_id', $this->generation->id)
            ->set('status', true)
            ->call('save')
            ->assertRedirect(route('admin.tvclub.members.index'));

        $this->assertDatabaseHas('tvclub_members', ['student_number' => '21.240.7005']);

        // Admin Production can create Group with Assignment
        $member = TVClubMember::where('student_number', '21.240.7005')->first();

        Livewire::actingAs($this->adminProduction)
            ->test(GroupForm::class)
            ->set('name', 'Group Prod Test')
            ->set('generation_id', $this->generation->id)
            ->set('program_id', $this->program->id)
            ->set('selectedMemberId', $member->id)
            ->set('selectedRole', 'Produser')
            ->call('addMember')
            ->call('save')
            ->assertRedirect(route('admin.tvclub.groups.index'));

        $this->assertDatabaseHas('tvclub_groups', ['name' => 'Group Prod Test']);
    }
}
