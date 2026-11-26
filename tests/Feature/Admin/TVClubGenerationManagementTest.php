<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\TVClub\Generations\Form;
use App\Livewire\Admin\TVClub\Generations\Index;
use App\Models\Program;
use App\Models\Role;
use App\Models\TVClubGeneration;
use App\Models\TVClubGroup;
use App\Models\TVClubMember;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class TVClubGenerationManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected User $superAdmin;

    protected User $adminProduction;

    protected User $adminNews;

    protected User $inactiveUser;

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
    }

    public function test_unauthenticated_user_cannot_access_generations(): void
    {
        $response = $this->get(route('admin.tvclub.generations.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_inactive_user_cannot_access_generations(): void
    {
        $response = $this->actingAs($this->inactiveUser)->get(route('admin.tvclub.generations.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_super_admin_can_view_generations(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('admin.tvclub.generations.index'));
        $response->assertStatus(200);
        $response->assertSee('Generasi TV Club');
        $response->assertSee('Tambah Generasi');
    }

    public function test_admin_production_can_view_generations(): void
    {
        $response = $this->actingAs($this->adminProduction)->get(route('admin.tvclub.generations.index'));
        $response->assertStatus(200);
        $response->assertSee('Generasi TV Club');
        $response->assertSee('Tambah Generasi');
    }

    public function test_admin_news_cannot_view_generations(): void
    {
        $this->actingAs($this->adminNews)->get(route('admin.tvclub.generations.index'))->assertForbidden();
    }

    public function test_super_admin_can_create_generation(): void
    {
        Livewire::actingAs($this->superAdmin)
            ->test(Form::class)
            ->set('name', 'Generasi 90 (2090)')
            ->set('year', 2090)
            ->set('description', 'Kru angkatan ke-90')
            ->set('status', true)
            ->call('save')
            ->assertRedirect(route('admin.tvclub.generations.index'));

        $this->assertDatabaseHas('tvclub_generations', [
            'name' => 'Generasi 90 (2090)',
            'year' => 2090,
            'description' => 'Kru angkatan ke-90',
            'status' => 1,
        ]);
    }

    public function test_admin_production_can_create_generation(): void
    {
        Livewire::actingAs($this->adminProduction)
            ->test(Form::class)
            ->set('name', 'Generasi 91 (2091)')
            ->set('year', 2091)
            ->set('description', 'Kru angkatan ke-91')
            ->set('status', true)
            ->call('save')
            ->assertRedirect(route('admin.tvclub.generations.index'));

        $this->assertDatabaseHas('tvclub_generations', [
            'name' => 'Generasi 91 (2091)',
            'year' => 2091,
        ]);
    }

    public function test_admin_news_cannot_create_generation_and_gets_403(): void
    {
        $response = $this->actingAs($this->adminNews)->get(route('admin.tvclub.generations.create'));
        $response->assertStatus(403);
    }

    public function test_duplicate_year_validation_works(): void
    {
        TVClubGeneration::create([
            'name' => 'Generasi 2083 Pertama',
            'year' => 2083,
            'status' => 1,
        ]);

        Livewire::actingAs($this->superAdmin)
            ->test(Form::class)
            ->set('name', 'Generasi 2083 Kedua')
            ->set('year', 2083)
            ->call('save')
            ->assertHasErrors(['year' => 'unique']);
    }

    public function test_super_admin_can_edit_generation(): void
    {
        $gen = TVClubGeneration::create([
            'name' => 'Generasi 2082',
            'year' => 2082,
            'description' => 'Keterangan awal',
            'status' => 1,
        ]);

        Livewire::actingAs($this->superAdmin)
            ->test(Form::class, ['generation' => $gen])
            ->set('name', 'Generasi 2082 Diperbarui')
            ->set('description', 'Keterangan baru')
            ->set('status', false)
            ->call('save')
            ->assertRedirect(route('admin.tvclub.generations.index'));

        $this->assertDatabaseHas('tvclub_generations', [
            'id' => $gen->id,
            'name' => 'Generasi 2082 Diperbarui',
            'description' => 'Keterangan baru',
            'status' => 0,
        ]);
    }

    public function test_admin_production_can_edit_generation(): void
    {
        $gen = TVClubGeneration::create([
            'name' => 'Generasi 2081',
            'year' => 2081,
            'status' => 1,
        ]);

        Livewire::actingAs($this->adminProduction)
            ->test(Form::class, ['generation' => $gen])
            ->set('name', 'Generasi 2081 Update Prod')
            ->call('save')
            ->assertRedirect(route('admin.tvclub.generations.index'));

        $this->assertDatabaseHas('tvclub_generations', [
            'id' => $gen->id,
            'name' => 'Generasi 2081 Update Prod',
        ]);
    }

    public function test_admin_news_cannot_edit_generation_and_gets_403(): void
    {
        $gen = TVClubGeneration::create([
            'name' => 'Generasi 2080',
            'year' => 2080,
            'status' => 1,
        ]);

        $response = $this->actingAs($this->adminNews)->get(route('admin.tvclub.generations.edit', $gen));
        $response->assertStatus(403);
    }

    public function test_super_admin_can_delete_unused_generation(): void
    {
        $gen = TVClubGeneration::create([
            'name' => 'Generasi Hapus SuperAdmin',
            'year' => 2079,
            'status' => 1,
        ]);

        Livewire::actingAs($this->superAdmin)
            ->test(Index::class)
            ->call('delete', $gen->id);

        $this->assertDatabaseMissing('tvclub_generations', [
            'id' => $gen->id,
        ]);
    }

    public function test_admin_production_can_delete_unused_generation(): void
    {
        $gen = TVClubGeneration::create([
            'name' => 'Generasi Hapus Prod',
            'year' => 2078,
            'status' => 1,
        ]);

        Livewire::actingAs($this->adminProduction)
            ->test(Index::class)
            ->call('delete', $gen->id);

        $this->assertDatabaseMissing('tvclub_generations', [
            'id' => $gen->id,
        ]);
    }

    public function test_admin_news_cannot_delete_generation(): void
    {
        $gen = TVClubGeneration::create([
            'name' => 'Generasi Dilarang Hapus News',
            'year' => 2077,
            'status' => 1,
        ]);

        $this->actingAs($this->adminNews)->get(route('admin.tvclub.generations.index'))->assertForbidden();

        $this->assertDatabaseHas('tvclub_generations', [
            'id' => $gen->id,
        ]);
    }

    public function test_generation_with_members_cannot_be_deleted(): void
    {
        $gen = TVClubGeneration::create([
            'name' => 'Generasi Ada Member',
            'year' => 2076,
            'status' => 1,
        ]);

        TVClubMember::create([
            'generation_id' => $gen->id,
            'student_number' => '21.240.0099',
            'name' => 'Kru Mahasiswa 1',
            'status' => 1,
        ]);

        Livewire::actingAs($this->superAdmin)
            ->test(Index::class)
            ->call('delete', $gen->id)
            ->assertSee('Generasi tidak dapat dihapus karena masih digunakan oleh anggota atau kelompok produksi.');

        $this->assertDatabaseHas('tvclub_generations', [
            'id' => $gen->id,
        ]);
    }

    public function test_generation_with_groups_cannot_be_deleted(): void
    {
        $program = Program::first() ?? Program::create([
            'name' => 'Program Tester',
            'slug' => 'program-tester',
            'status' => 1,
        ]);

        $gen = TVClubGeneration::create([
            'name' => 'Generasi Ada Grup',
            'year' => 2075,
            'status' => 1,
        ]);

        TVClubGroup::create([
            'generation_id' => $gen->id,
            'program_id' => $program->id,
            'name' => 'Tim Produksi Ngopot',
            'status' => 1,
        ]);

        Livewire::actingAs($this->superAdmin)
            ->test(Index::class)
            ->call('delete', $gen->id)
            ->assertSee('Generasi tidak dapat dihapus karena masih digunakan oleh anggota atau kelompok produksi.');

        $this->assertDatabaseHas('tvclub_generations', [
            'id' => $gen->id,
        ]);
    }

    public function test_search_works(): void
    {
        $gen = TVClubGeneration::create([
            'name' => 'Generasi Unik Khusus Search',
            'year' => 2074,
            'status' => 1,
        ]);

        Livewire::actingAs($this->superAdmin)
            ->test(Index::class)
            ->set('search', 'Unik Khusus')
            ->assertSee('Generasi Unik Khusus Search')
            ->set('search', '2074')
            ->assertSee('Generasi Unik Khusus Search')
            ->set('search', 'KataKunciTidakAda')
            ->assertDontSee('Generasi Unik Khusus Search');
    }

    public function test_sorting_works(): void
    {
        TVClubGeneration::create(['name' => 'Generasi A', 'year' => 2071, 'status' => 1]);
        TVClubGeneration::create(['name' => 'Generasi Z', 'year' => 2072, 'status' => 1]);

        Livewire::actingAs($this->superAdmin)
            ->test(Index::class)
            ->call('sortBy', 'name')
            ->assertSet('sortField', 'name')
            ->assertSet('sortDirection', 'asc');
    }

    public function test_pagination_works(): void
    {
        Livewire::actingAs($this->superAdmin)
            ->test(Index::class)
            ->set('perPage', 25)
            ->assertSet('perPage', 25);
    }
}
