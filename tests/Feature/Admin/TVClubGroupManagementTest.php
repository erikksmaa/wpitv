<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\TVClub\Groups\Form;
use App\Livewire\Admin\TVClub\Groups\Index;
use App\Models\Program;
use App\Models\Role;
use App\Models\TVClubGeneration;
use App\Models\TVClubGroup;
use App\Models\TVClubMember;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class TVClubGroupManagementTest extends TestCase
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
            'name' => 'Super Admin Tester',
            'role_id' => $superAdminRole->id,
            'status' => 1,
        ]);

        $this->adminProduction = User::factory()->create([
            'name' => 'Admin Production Tester',
            'role_id' => $prodRole->id,
            'status' => 1,
        ]);

        $this->adminNews = User::factory()->create([
            'name' => 'Admin News Tester',
            'role_id' => $newsRole->id,
            'status' => 1,
        ]);

        $this->inactiveUser = User::factory()->create([
            'role_id' => $superAdminRole->id,
            'status' => 0,
        ]);

        $this->generation = TVClubGeneration::where('year', 2025)->first() ?? TVClubGeneration::create([
            'name' => 'Generasi Group Test 2025',
            'year' => 2085,
            'description' => 'Generasi testing untuk group',
            'status' => 1,
        ]);

        $this->program = Program::first() ?? Program::create([
            'name' => 'Program Group Test',
            'slug' => 'program-group-test',
            'status' => 1,
        ]);
    }

    public function test_unauthenticated_user_cannot_access_groups(): void
    {
        $response = $this->get(route('admin.tvclub.groups.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_inactive_user_cannot_access_groups(): void
    {
        $response = $this->actingAs($this->inactiveUser)->get(route('admin.tvclub.groups.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_super_admin_can_view_groups(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('admin.tvclub.groups.index'));
        $response->assertStatus(200);
        $response->assertSee('Kelompok TV Club');
        $response->assertSee('Tambah Kelompok');
        $response->assertSee('Total Kelompok');
    }

    public function test_admin_production_can_view_groups(): void
    {
        $response = $this->actingAs($this->adminProduction)->get(route('admin.tvclub.groups.index'));
        $response->assertStatus(200);
        $response->assertSee('Kelompok TV Club');
        $response->assertSee('Tambah Kelompok');
    }

    public function test_admin_news_cannot_view_groups(): void
    {
        $this->actingAs($this->adminNews)->get(route('admin.tvclub.groups.index'))->assertForbidden();
    }

    public function test_super_admin_can_create_group(): void
    {
        Livewire::actingAs($this->superAdmin)
            ->test(Form::class)
            ->set('generation_id', $this->generation->id)
            ->set('program_id', $this->program->id)
            ->set('name', 'Tim Produksi Talkshow Gen 2025')
            ->set('description', 'Kru talkshow')
            ->set('status', true)
            ->call('save')
            ->assertRedirect(route('admin.tvclub.groups.index'));

        $this->assertDatabaseHas('tvclub_groups', [
            'generation_id' => $this->generation->id,
            'program_id' => $this->program->id,
            'name' => 'Tim Produksi Talkshow Gen 2025',
            'status' => 1,
        ]);
    }

    public function test_admin_production_can_create_group(): void
    {
        Livewire::actingAs($this->adminProduction)
            ->test(Form::class)
            ->set('generation_id', $this->generation->id)
            ->set('program_id', $this->program->id)
            ->set('name', 'Tim Produksi Podcast Prod')
            ->set('status', true)
            ->call('save')
            ->assertRedirect(route('admin.tvclub.groups.index'));

        $this->assertDatabaseHas('tvclub_groups', [
            'name' => 'Tim Produksi Podcast Prod',
        ]);
    }

    public function test_admin_news_cannot_create_group_and_gets_403(): void
    {
        $response = $this->actingAs($this->adminNews)->get(route('admin.tvclub.groups.create'));
        $response->assertStatus(403);
    }

    public function test_super_admin_can_edit_group(): void
    {
        $group = TVClubGroup::create([
            'generation_id' => $this->generation->id,
            'program_id' => $this->program->id,
            'name' => 'Tim Awal',
            'status' => 1,
        ]);

        Livewire::actingAs($this->superAdmin)
            ->test(Form::class, ['group' => $group])
            ->set('name', 'Tim Diperbarui')
            ->set('status', false)
            ->call('save')
            ->assertRedirect(route('admin.tvclub.groups.index'));

        $this->assertDatabaseHas('tvclub_groups', [
            'id' => $group->id,
            'name' => 'Tim Diperbarui',
            'status' => 0,
        ]);
    }

    public function test_admin_production_can_edit_group(): void
    {
        $group = TVClubGroup::create([
            'generation_id' => $this->generation->id,
            'program_id' => $this->program->id,
            'name' => 'Tim Awal Prod',
            'status' => 1,
        ]);

        Livewire::actingAs($this->adminProduction)
            ->test(Form::class, ['group' => $group])
            ->set('name', 'Tim Update Prod')
            ->call('save')
            ->assertRedirect(route('admin.tvclub.groups.index'));

        $this->assertDatabaseHas('tvclub_groups', [
            'id' => $group->id,
            'name' => 'Tim Update Prod',
        ]);
    }

    public function test_admin_news_cannot_edit_group_and_gets_403(): void
    {
        $group = TVClubGroup::create([
            'generation_id' => $this->generation->id,
            'program_id' => $this->program->id,
            'name' => 'Tim News Deny',
            'status' => 1,
        ]);

        $response = $this->actingAs($this->adminNews)->get(route('admin.tvclub.groups.edit', $group));
        $response->assertStatus(403);
    }

    public function test_super_admin_can_delete_empty_group(): void
    {
        $group = TVClubGroup::create([
            'generation_id' => $this->generation->id,
            'program_id' => $this->program->id,
            'name' => 'Tim Kosong Hapus',
            'status' => 1,
        ]);

        Livewire::actingAs($this->superAdmin)
            ->test(Index::class)
            ->call('delete', $group->id);

        $this->assertDatabaseMissing('tvclub_groups', [
            'id' => $group->id,
        ]);
    }

    public function test_admin_production_can_delete_empty_group(): void
    {
        $group = TVClubGroup::create([
            'generation_id' => $this->generation->id,
            'program_id' => $this->program->id,
            'name' => 'Tim Kosong Hapus Prod',
            'status' => 1,
        ]);

        Livewire::actingAs($this->adminProduction)
            ->test(Index::class)
            ->call('delete', $group->id);

        $this->assertDatabaseMissing('tvclub_groups', [
            'id' => $group->id,
        ]);
    }

    public function test_admin_news_cannot_delete_group(): void
    {
        $group = TVClubGroup::create([
            'generation_id' => $this->generation->id,
            'program_id' => $this->program->id,
            'name' => 'Tim News Deny Delete',
            'status' => 1,
        ]);

        $this->actingAs($this->adminNews)->get(route('admin.tvclub.groups.index'))->assertForbidden();

        $this->assertDatabaseHas('tvclub_groups', [
            'id' => $group->id,
        ]);
    }

    public function test_group_with_members_cannot_be_deleted(): void
    {
        $group = TVClubGroup::create([
            'generation_id' => $this->generation->id,
            'program_id' => $this->program->id,
            'name' => 'Tim Ada Anggota',
            'status' => 1,
        ]);

        $member = TVClubMember::create([
            'generation_id' => $this->generation->id,
            'student_number' => '21.240.8001',
            'name' => 'Kru Dalam Grup',
            'status' => 1,
        ]);

        $group->members()->attach($member->id, ['role' => 'Editor']);

        Livewire::actingAs($this->superAdmin)
            ->test(Index::class)
            ->call('delete', $group->id)
            ->assertSee('Kelompok ini masih memiliki anggota terdaftar. Hapus seluruh penugasan anggota sebelum menghapus kelompok.');

        $this->assertDatabaseHas('tvclub_groups', [
            'id' => $group->id,
        ]);
    }

    public function test_search_works(): void
    {
        $group = TVClubGroup::create([
            'generation_id' => $this->generation->id,
            'program_id' => $this->program->id,
            'name' => 'Tim Unik Pencarian Khusus',
            'description' => 'Deskripsi rahasia',
            'status' => 1,
        ]);

        Livewire::actingAs($this->superAdmin)
            ->test(Index::class)
            ->set('search', 'Pencarian Khusus')
            ->assertSee('Tim Unik Pencarian Khusus')
            ->set('search', 'KataKunciTidakAda')
            ->assertDontSee('Tim Unik Pencarian Khusus');
    }

    public function test_generation_filter_works(): void
    {
        $gen2 = TVClubGeneration::where('year', 2026)->first() ?? TVClubGeneration::create([
            'name' => 'Generasi Filter 2026',
            'year' => 2086,
            'status' => 1,
        ]);

        $group1 = TVClubGroup::create([
            'generation_id' => $this->generation->id,
            'program_id' => $this->program->id,
            'name' => 'Grup Gen 1',
            'status' => 1,
        ]);

        $group2 = TVClubGroup::create([
            'generation_id' => $gen2->id,
            'program_id' => $this->program->id,
            'name' => 'Grup Gen 2',
            'status' => 1,
        ]);

        Livewire::actingAs($this->superAdmin)
            ->test(Index::class)
            ->set('generationFilter', (string) $gen2->id)
            ->assertSee('Grup Gen 2')
            ->assertDontSee('Grup Gen 1');
    }

    public function test_program_filter_works(): void
    {
        $prog2 = Program::create([
            'name' => 'Program Lain Filter',
            'slug' => 'program-lain-filter',
            'status' => 1,
        ]);

        $group1 = TVClubGroup::create([
            'generation_id' => $this->generation->id,
            'program_id' => $this->program->id,
            'name' => 'Grup Prog 1',
            'status' => 1,
        ]);

        $group2 = TVClubGroup::create([
            'generation_id' => $this->generation->id,
            'program_id' => $prog2->id,
            'name' => 'Grup Prog 2',
            'status' => 1,
        ]);

        Livewire::actingAs($this->superAdmin)
            ->test(Index::class)
            ->set('programFilter', (string) $prog2->id)
            ->assertSee('Grup Prog 2')
            ->assertDontSee('Grup Prog 1');
    }

    public function test_status_filter_works(): void
    {
        $group1 = TVClubGroup::create([
            'generation_id' => $this->generation->id,
            'program_id' => $this->program->id,
            'name' => 'Grup Aktif',
            'status' => 1,
        ]);

        $group2 = TVClubGroup::create([
            'generation_id' => $this->generation->id,
            'program_id' => $this->program->id,
            'name' => 'Grup Inaktif',
            'status' => 0,
        ]);

        Livewire::actingAs($this->superAdmin)
            ->test(Index::class)
            ->set('statusFilter', '0')
            ->assertSee('Grup Inaktif')
            ->assertDontSee('Grup Aktif');
    }

    public function test_sorting_works(): void
    {
        Livewire::actingAs($this->superAdmin)
            ->test(Index::class)
            ->call('sortBy', 'program')
            ->assertSet('sortField', 'program')
            ->assertSet('sortDirection', 'asc');
    }

    public function test_pagination_works(): void
    {
        Livewire::actingAs($this->superAdmin)
            ->test(Index::class)
            ->set('perPage', 25)
            ->assertSet('perPage', 25);
    }

    public function test_validation_works(): void
    {
        Livewire::actingAs($this->superAdmin)
            ->test(Form::class)
            ->set('name', '')
            ->set('generation_id', 999999)
            ->set('program_id', 999999)
            ->call('save')
            ->assertHasErrors(['name', 'generation_id', 'program_id']);
    }

    public function test_member_assignment_and_role_assignment_works(): void
    {
        $member = TVClubMember::create([
            'generation_id' => $this->generation->id,
            'student_number' => '21.240.9001',
            'name' => 'Kru Assign Test',
            'status' => 1,
        ]);

        Livewire::actingAs($this->superAdmin)
            ->test(Form::class)
            ->set('generation_id', $this->generation->id)
            ->set('program_id', $this->program->id)
            ->set('name', 'Grup Dengan Kru')
            ->set('selectedMemberId', $member->id)
            ->set('selectedRole', 'Produser')
            ->call('addMember')
            ->call('save')
            ->assertRedirect(route('admin.tvclub.groups.index'));

        $group = TVClubGroup::where('name', 'Grup Dengan Kru')->first();
        $this->assertNotNull($group);
        $this->assertDatabaseHas('tvclub_group_members', [
            'group_id' => $group->id,
            'member_id' => $member->id,
            'role' => 'Produser',
        ]);
    }

    public function test_duplicate_member_assignment_is_prevented(): void
    {
        $member = TVClubMember::create([
            'generation_id' => $this->generation->id,
            'student_number' => '21.240.9002',
            'name' => 'Kru Duplikat Test',
            'status' => 1,
        ]);

        Livewire::actingAs($this->superAdmin)
            ->test(Form::class)
            ->set('generation_id', $this->generation->id)
            ->set('program_id', $this->program->id)
            ->set('name', 'Grup Duplikat')
            ->set('selectedMemberId', $member->id)
            ->call('addMember')
            ->set('selectedMemberId', $member->id)
            ->call('addMember')
            ->assertSee('Anggota ini sudah terdaftar dalam penugasan kelompok.');
    }

    public function test_member_from_different_generation_cannot_be_assigned(): void
    {
        $otherGen = TVClubGeneration::where('year', 2024)->first() ?? TVClubGeneration::create([
            'name' => 'Generasi Beda 2024',
            'year' => 2084,
            'status' => 1,
        ]);

        $memberOtherGen = TVClubMember::create([
            'generation_id' => $otherGen->id,
            'student_number' => '21.240.9003',
            'name' => 'Kru Beda Gen',
            'status' => 1,
        ]);

        Livewire::actingAs($this->superAdmin)
            ->test(Form::class)
            ->set('generation_id', $this->generation->id)
            ->set('selectedMemberId', $memberOtherGen->id)
            ->call('addMember')
            ->assertSee('Anggota harus berasal dari generasi yang sama dengan kelompok produksi ini.');
    }

    public function test_remove_member_assignment_works(): void
    {
        $member = TVClubMember::create([
            'generation_id' => $this->generation->id,
            'student_number' => '21.240.9004',
            'name' => 'Kru Lepas Test',
            'status' => 1,
        ]);

        $group = TVClubGroup::create([
            'generation_id' => $this->generation->id,
            'program_id' => $this->program->id,
            'name' => 'Grup Lepas Kru',
            'status' => 1,
        ]);

        $group->members()->attach($member->id, ['role' => 'Editor']);

        Livewire::actingAs($this->superAdmin)
            ->test(Form::class, ['group' => $group])
            ->call('removeMember', $member->id)
            ->call('save')
            ->assertRedirect(route('admin.tvclub.groups.index'));

        $this->assertDatabaseMissing('tvclub_group_members', [
            'group_id' => $group->id,
            'member_id' => $member->id,
        ]);

        // Ensure member record itself was not deleted
        $this->assertDatabaseHas('tvclub_members', [
            'id' => $member->id,
        ]);
    }

    public function test_unauthorized_user_cannot_modify_assignment(): void
    {
        $group = TVClubGroup::create([
            'generation_id' => $this->generation->id,
            'program_id' => $this->program->id,
            'name' => 'Grup Auth Check',
            'status' => 1,
        ]);

        Livewire::actingAs($this->adminNews)
            ->test(Form::class, ['group' => $group])
            ->assertStatus(403);
    }

    public function test_group_members_count_works(): void
    {
        $group = TVClubGroup::create([
            'generation_id' => $this->generation->id,
            'program_id' => $this->program->id,
            'name' => 'Grup Hitung Kru',
            'status' => 1,
        ]);

        $member1 = TVClubMember::create([
            'generation_id' => $this->generation->id,
            'student_number' => '21.240.9005',
            'name' => 'Kru 1',
            'status' => 1,
        ]);

        $member2 = TVClubMember::create([
            'generation_id' => $this->generation->id,
            'student_number' => '21.240.9006',
            'name' => 'Kru 2',
            'status' => 1,
        ]);

        $group->members()->attach([
            $member1->id => ['role' => 'Produser'],
            $member2->id => ['role' => 'Kameramen'],
        ]);

        Livewire::actingAs($this->superAdmin)
            ->test(Index::class)
            ->assertSee('2 Anggota');
    }

    public function test_generation_relationship_works(): void
    {
        $group = TVClubGroup::create([
            'generation_id' => $this->generation->id,
            'program_id' => $this->program->id,
            'name' => 'Grup Relasi Gen',
            'status' => 1,
        ]);

        $this->assertEquals($this->generation->name, $group->generation->name);
    }

    public function test_program_relationship_works(): void
    {
        $group = TVClubGroup::create([
            'generation_id' => $this->generation->id,
            'program_id' => $this->program->id,
            'name' => 'Grup Relasi Prog',
            'status' => 1,
        ]);

        $this->assertEquals($this->program->name, $group->program->name);
    }
}
