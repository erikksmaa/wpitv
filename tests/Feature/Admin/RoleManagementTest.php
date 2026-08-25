<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Roles\Form;
use App\Livewire\Admin\Roles\Index;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected User $superAdmin;

    protected User $adminProduction;

    protected User $adminNews;

    protected User $inactiveUser;

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
    }

    public function test_guest_cannot_access_roles(): void
    {
        $this->get(route('admin.roles.index'))->assertRedirect(route('login'));
    }

    public function test_inactive_user_cannot_access_roles(): void
    {
        $this->actingAs($this->inactiveUser)->get(route('admin.roles.index'))->assertRedirect(route('login'));
    }

    public function test_super_admin_can_view_roles(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('admin.roles.index'));
        $response->assertStatus(200);
        $response->assertSee('Manajemen Peran');
        $response->assertSee('Tambah Peran');
        $response->assertSee('Super Admin');
    }

    public function test_admin_production_cannot_manage_roles_and_gets_403(): void
    {
        $this->actingAs($this->adminProduction)->get(route('admin.roles.index'))->assertStatus(403);
    }

    public function test_admin_news_cannot_manage_roles_and_gets_403(): void
    {
        $this->actingAs($this->adminNews)->get(route('admin.roles.index'))->assertStatus(403);
    }

    public function test_super_admin_can_create_role(): void
    {
        Livewire::actingAs($this->superAdmin)
            ->test(Form::class)
            ->set('name', 'Admin Broadcaster Test')
            ->set('description', 'Role pengelola siaran langsung')
            ->call('save')
            ->assertRedirect(route('admin.roles.index'));

        $this->assertDatabaseHas('roles', [
            'name' => 'Admin Broadcaster Test',
            'description' => 'Role pengelola siaran langsung',
        ]);
    }

    public function test_validation_works(): void
    {
        Livewire::actingAs($this->superAdmin)
            ->test(Form::class)
            ->set('name', '')
            ->call('save')
            ->assertHasErrors(['name' => 'required']);
    }

    public function test_duplicate_role_is_prevented(): void
    {
        Livewire::actingAs($this->superAdmin)
            ->test(Form::class)
            ->set('name', 'Super Admin')
            ->call('save')
            ->assertHasErrors(['name' => 'unique']);
    }

    public function test_super_admin_can_edit_role(): void
    {
        $role = Role::create([
            'name' => 'Role Edit Test',
            'description' => 'Deskripsi lama',
        ]);

        Livewire::actingAs($this->superAdmin)
            ->test(Form::class, ['role' => $role])
            ->set('name', 'Role Edit Selesai')
            ->set('description', 'Deskripsi baru yang diperbarui')
            ->call('save')
            ->assertRedirect(route('admin.roles.index'));

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'name' => 'Role Edit Selesai',
            'description' => 'Deskripsi baru yang diperbarui',
        ]);
    }

    public function test_role_used_by_users_cannot_be_deleted(): void
    {
        $role = Role::create([
            'name' => 'Role In Use Test',
        ]);

        User::factory()->create([
            'role_id' => $role->id,
            'status' => 1,
        ]);

        Livewire::actingAs($this->superAdmin)
            ->test(Index::class)
            ->call('delete', $role->id)
            ->assertSee('Role cannot be deleted because it is currently assigned to one or more users.');

        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    public function test_core_system_roles_cannot_be_deleted(): void
    {
        $superAdminRole = Role::where('name', 'Super Admin')->first();

        Livewire::actingAs($this->superAdmin)
            ->test(Index::class)
            ->call('delete', $superAdminRole->id)
            ->assertSee("Role 'Super Admin' adalah role inti sistem dan tidak dapat dihapus.");

        $this->assertDatabaseHas('roles', ['id' => $superAdminRole->id]);
    }

    public function test_unauthorized_direct_url_returns_403(): void
    {
        $this->actingAs($this->adminProduction)->get(route('admin.roles.index'))->assertStatus(403);
        $this->actingAs($this->adminProduction)->get(route('admin.roles.create'))->assertStatus(403);
        $this->actingAs($this->adminNews)->get(route('admin.roles.index'))->assertStatus(403);
        $this->actingAs($this->adminNews)->get(route('admin.roles.create'))->assertStatus(403);
    }

    public function test_unauthorized_livewire_mutation_returns_403(): void
    {
        $role = Role::create(['name' => 'Role Unauth Test']);

        $this->actingAs($this->adminNews)->get(route('admin.roles.edit', $role))->assertStatus(403);
        $this->actingAs($this->adminProduction)->get(route('admin.roles.edit', $role))->assertStatus(403);
    }

    public function test_user_count_per_role_works(): void
    {
        $role = Role::create(['name' => 'Role Count Test']);

        User::factory()->create(['role_id' => $role->id, 'status' => 1]);
        User::factory()->create(['role_id' => $role->id, 'status' => 1]);

        Livewire::actingAs($this->superAdmin)
            ->test(Index::class)
            ->assertSee('2 Pengguna');
    }
}
