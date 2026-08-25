<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\TVClub\Members\Form;
use App\Livewire\Admin\TVClub\Members\Index;
use App\Models\Program;
use App\Models\Role;
use App\Models\TVClubGeneration;
use App\Models\TVClubGroup;
use App\Models\TVClubMember;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class TVClubMemberManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected User $superAdmin;

    protected User $adminProduction;

    protected User $adminNews;

    protected User $inactiveUser;

    protected TVClubGeneration $generation;

    protected function setUp(): void
    {
        parent::setUp();

        $superAdminRole = Role::where('name', 'Super Admin')->first() ?? Role::find(1);
        $prodRole = Role::where('name', 'Admin Production')->first() ?? Role::find(2);
        $newsRole = Role::where('name', 'Admin News')->first() ?? Role::find(3);

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
            'name' => 'Generasi Test 2025',
            'year' => 2085,
            'description' => 'Generasi testing untuk member',
            'status' => 1,
        ]);
    }

    public function test_unauthenticated_user_cannot_access_members(): void
    {
        $response = $this->get(route('admin.tvclub.members.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_inactive_user_cannot_access_members(): void
    {
        $response = $this->actingAs($this->inactiveUser)->get(route('admin.tvclub.members.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_super_admin_can_view_members(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('admin.tvclub.members.index'));
        $response->assertStatus(200);
        $response->assertSee('Anggota TV Club');
        $response->assertSee('Tambah Anggota');
        $response->assertSee('Total Anggota');
    }

    public function test_admin_production_can_view_members(): void
    {
        $response = $this->actingAs($this->adminProduction)->get(route('admin.tvclub.members.index'));
        $response->assertStatus(200);
        $response->assertSee('Anggota TV Club');
        $response->assertSee('Tambah Anggota');
    }

    public function test_admin_news_can_view_members_but_cannot_see_create_or_action_buttons(): void
    {
        $response = $this->actingAs($this->adminNews)->get(route('admin.tvclub.members.index'));
        $response->assertStatus(200);
        $response->assertSee('Anggota TV Club');
        $response->assertDontSee('Tambah Anggota');

        Livewire::actingAs($this->adminNews)
            ->test(Index::class)
            ->assertDontSeeHtml('wire:click="delete(')
            ->assertDontSeeHtml('title="Edit Anggota"');
    }

    public function test_super_admin_can_create_member(): void
    {
        Livewire::actingAs($this->superAdmin)
            ->test(Form::class)
            ->set('generation_id', $this->generation->id)
            ->set('student_number', '21.240.0001')
            ->set('name', 'Budi Santoso')
            ->set('status', true)
            ->call('save')
            ->assertRedirect(route('admin.tvclub.members.index'));

        $this->assertDatabaseHas('tvclub_members', [
            'generation_id' => $this->generation->id,
            'student_number' => '21.240.0001',
            'name' => 'Budi Santoso',
            'status' => 1,
        ]);
    }

    public function test_admin_production_can_create_member(): void
    {
        Livewire::actingAs($this->adminProduction)
            ->test(Form::class)
            ->set('generation_id', $this->generation->id)
            ->set('student_number', '21.240.0002')
            ->set('name', 'Siti Rahma')
            ->set('status', true)
            ->call('save')
            ->assertRedirect(route('admin.tvclub.members.index'));

        $this->assertDatabaseHas('tvclub_members', [
            'generation_id' => $this->generation->id,
            'student_number' => '21.240.0002',
            'name' => 'Siti Rahma',
        ]);
    }

    public function test_admin_news_cannot_create_member_and_gets_403(): void
    {
        $response = $this->actingAs($this->adminNews)->get(route('admin.tvclub.members.create'));
        $response->assertStatus(403);
    }

    public function test_super_admin_can_edit_member(): void
    {
        $member = TVClubMember::create([
            'generation_id' => $this->generation->id,
            'student_number' => '21.240.0003',
            'name' => 'Ahmad Awal',
            'status' => 1,
        ]);

        Livewire::actingAs($this->superAdmin)
            ->test(Form::class, ['member' => $member])
            ->set('name', 'Ahmad Diperbarui')
            ->set('status', false)
            ->call('save')
            ->assertRedirect(route('admin.tvclub.members.index'));

        $this->assertDatabaseHas('tvclub_members', [
            'id' => $member->id,
            'name' => 'Ahmad Diperbarui',
            'status' => 0,
        ]);
    }

    public function test_admin_production_can_edit_member(): void
    {
        $member = TVClubMember::create([
            'generation_id' => $this->generation->id,
            'student_number' => '21.240.0004',
            'name' => 'Rian Prod',
            'status' => 1,
        ]);

        Livewire::actingAs($this->adminProduction)
            ->test(Form::class, ['member' => $member])
            ->set('name', 'Rian Prod Updated')
            ->call('save')
            ->assertRedirect(route('admin.tvclub.members.index'));

        $this->assertDatabaseHas('tvclub_members', [
            'id' => $member->id,
            'name' => 'Rian Prod Updated',
        ]);
    }

    public function test_admin_news_cannot_edit_member_and_gets_403(): void
    {
        $member = TVClubMember::create([
            'generation_id' => $this->generation->id,
            'student_number' => '21.240.0005',
            'name' => 'Member News Deny',
            'status' => 1,
        ]);

        $response = $this->actingAs($this->adminNews)->get(route('admin.tvclub.members.edit', $member));
        $response->assertStatus(403);
    }

    public function test_super_admin_can_delete_unused_member(): void
    {
        $member = TVClubMember::create([
            'generation_id' => $this->generation->id,
            'student_number' => '21.240.0006',
            'name' => 'Member To Delete',
            'status' => 1,
        ]);

        Livewire::actingAs($this->superAdmin)
            ->test(Index::class)
            ->call('delete', $member->id);

        $this->assertDatabaseMissing('tvclub_members', [
            'id' => $member->id,
        ]);
    }

    public function test_admin_production_can_delete_unused_member(): void
    {
        $member = TVClubMember::create([
            'generation_id' => $this->generation->id,
            'student_number' => '21.240.0007',
            'name' => 'Member Delete Prod',
            'status' => 1,
        ]);

        Livewire::actingAs($this->adminProduction)
            ->test(Index::class)
            ->call('delete', $member->id);

        $this->assertDatabaseMissing('tvclub_members', [
            'id' => $member->id,
        ]);
    }

    public function test_admin_news_cannot_delete_member(): void
    {
        $member = TVClubMember::create([
            'generation_id' => $this->generation->id,
            'student_number' => '21.240.0008',
            'name' => 'Member Deny News Delete',
            'status' => 1,
        ]);

        Livewire::actingAs($this->adminNews)
            ->test(Index::class)
            ->call('delete', $member->id)
            ->assertStatus(403);

        $this->assertDatabaseHas('tvclub_members', [
            'id' => $member->id,
        ]);
    }

    public function test_member_with_group_assignment_cannot_be_deleted(): void
    {
        $program = Program::first() ?? Program::create([
            'name' => 'Program Test Member',
            'slug' => 'program-test-member',
            'status' => 1,
        ]);

        $group = TVClubGroup::create([
            'generation_id' => $this->generation->id,
            'program_id' => $program->id,
            'name' => 'Tim Produksi News 1',
            'status' => 1,
        ]);

        $member = TVClubMember::create([
            'generation_id' => $this->generation->id,
            'student_number' => '21.240.0009',
            'name' => 'Member Di Grup',
            'status' => 1,
        ]);

        $member->groups()->attach($group->id, ['role' => 'Kameramen']);

        Livewire::actingAs($this->superAdmin)
            ->test(Index::class)
            ->call('delete', $member->id)
            ->assertSee('Member cannot be deleted because this member is still assigned to one or more production groups.');

        $this->assertDatabaseHas('tvclub_members', [
            'id' => $member->id,
        ]);
    }

    public function test_search_works_by_nim_and_name(): void
    {
        TVClubMember::create([
            'generation_id' => $this->generation->id,
            'student_number' => '99.888.7777',
            'name' => 'Anggota Spesifik Search',
            'status' => 1,
        ]);

        Livewire::actingAs($this->superAdmin)
            ->test(Index::class)
            ->set('search', '99.888.7777')
            ->assertSee('Anggota Spesifik Search')
            ->set('search', 'Spesifik Search')
            ->assertSee('99.888.7777')
            ->set('search', 'NIMYangTidakAda')
            ->assertDontSee('Anggota Spesifik Search');
    }

    public function test_generation_filter_works(): void
    {
        $gen2 = TVClubGeneration::where('year', 2026)->first() ?? TVClubGeneration::create([
            'name' => 'Generasi Filter 2',
            'year' => 2086,
            'status' => 1,
        ]);

        TVClubMember::create([
            'generation_id' => $this->generation->id,
            'student_number' => '21.240.1001',
            'name' => 'Member Gen 1',
            'status' => 1,
        ]);

        TVClubMember::create([
            'generation_id' => $gen2->id,
            'student_number' => '21.240.1002',
            'name' => 'Member Gen 2',
            'status' => 1,
        ]);

        Livewire::actingAs($this->superAdmin)
            ->test(Index::class)
            ->set('generationFilter', (string) $gen2->id)
            ->assertSee('Member Gen 2')
            ->assertDontSee('Member Gen 1');
    }

    public function test_status_filter_works(): void
    {
        TVClubMember::create([
            'generation_id' => $this->generation->id,
            'student_number' => '21.240.2001',
            'name' => 'Member Aktif Filter',
            'status' => 1,
        ]);

        TVClubMember::create([
            'generation_id' => $this->generation->id,
            'student_number' => '21.240.2002',
            'name' => 'Member Inaktif Filter',
            'status' => 0,
        ]);

        Livewire::actingAs($this->superAdmin)
            ->test(Index::class)
            ->set('statusFilter', '0')
            ->assertSee('Member Inaktif Filter')
            ->assertDontSee('Member Aktif Filter');
    }

    public function test_sorting_works(): void
    {
        Livewire::actingAs($this->superAdmin)
            ->test(Index::class)
            ->call('sortBy', 'student_number')
            ->assertSet('sortField', 'student_number')
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
            ->set('student_number', '')
            ->set('name', '')
            ->set('generation_id', 999999)
            ->call('save')
            ->assertHasErrors(['student_number', 'name', 'generation_id']);
    }

    public function test_photo_upload_and_storage_works(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('avatar.jpg', 200, 200);

        Livewire::actingAs($this->superAdmin)
            ->test(Form::class)
            ->set('generation_id', $this->generation->id)
            ->set('student_number', '21.240.3001')
            ->set('name', 'Budi Foto')
            ->set('photo', $file)
            ->set('status', true)
            ->call('save')
            ->assertRedirect(route('admin.tvclub.members.index'));

        $member = TVClubMember::where('student_number', '21.240.3001')->first();
        $this->assertNotNull($member);
        $this->assertNotNull($member->photo);
        Storage::disk('public')->assertExists('tvclub/members/'.$member->photo);
    }

    public function test_fallback_photo_and_initials_works(): void
    {
        $member = new TVClubMember([
            'name' => 'Fajar Nugraha',
            'photo' => null,
        ]);

        $this->assertNull($member->photo_url);
        $this->assertEquals('FN', $member->initials);
    }

    public function test_generation_relationship_displays_correctly(): void
    {
        $member = TVClubMember::create([
            'generation_id' => $this->generation->id,
            'student_number' => '21.240.4001',
            'name' => 'Relasi Mahasiswa',
            'status' => 1,
        ]);

        $this->assertEquals($this->generation->name, $member->generation->name);
    }

    public function test_groups_count_works(): void
    {
        $program = Program::first() ?? Program::create([
            'name' => 'Program Hitung Grup',
            'slug' => 'program-hitung-grup',
            'status' => 1,
        ]);

        $group = TVClubGroup::create([
            'generation_id' => $this->generation->id,
            'program_id' => $program->id,
            'name' => 'Tim Produksi Talkshow',
            'status' => 1,
        ]);

        $member = TVClubMember::create([
            'generation_id' => $this->generation->id,
            'student_number' => '21.240.5001',
            'name' => 'Kru Dua Kelompok',
            'status' => 1,
        ]);

        $member->groups()->attach($group->id, ['role' => 'Produser']);

        Livewire::actingAs($this->superAdmin)
            ->test(Index::class)
            ->assertSee('1 Kelompok');
    }
}
