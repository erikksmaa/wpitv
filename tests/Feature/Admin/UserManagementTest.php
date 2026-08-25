<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Users\Form;
use App\Livewire\Admin\Users\Index;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected User $superAdmin;

    protected User $adminProduction;

    protected User $adminNews;

    protected User $inactiveUser;

    protected Role $superAdminRole;

    protected Role $prodRole;

    protected Role $newsRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdminRole = Role::where('name', 'Super Admin')->first() ?? Role::find(1);
        $this->prodRole = Role::where('name', 'Admin Production')->first() ?? Role::find(2);
        $this->newsRole = Role::where('name', 'Admin News')->first() ?? Role::find(3);

        $this->superAdmin = User::factory()->create([
            'username' => 'super_admin_test',
            'name' => 'Super Admin Tester',
            'role_id' => $this->superAdminRole->id,
            'status' => 1,
        ]);

        $this->adminProduction = User::factory()->create([
            'username' => 'prod_test',
            'name' => 'Admin Production Tester',
            'role_id' => $this->prodRole->id,
            'status' => 1,
        ]);

        $this->adminNews = User::factory()->create([
            'username' => 'news_test',
            'name' => 'Admin News Tester',
            'role_id' => $this->newsRole->id,
            'status' => 1,
        ]);

        $this->inactiveUser = User::factory()->create([
            'username' => 'inactive_test',
            'role_id' => $this->superAdminRole->id,
            'status' => 0,
        ]);
    }

    public function test_guest_cannot_access_users(): void
    {
        $this->get(route('admin.users.index'))->assertRedirect(route('login'));
    }

    public function test_inactive_user_cannot_access_users(): void
    {
        $this->actingAs($this->inactiveUser)->get(route('admin.users.index'))->assertRedirect(route('login'));
    }

    public function test_super_admin_can_view_users(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('admin.users.index'));
        $response->assertStatus(200);
        $response->assertSee('Manajemen Pengguna');
        $response->assertSee('Tambah Pengguna');
        $response->assertSee('Total Pengguna');
    }

    public function test_admin_production_cannot_manage_users_and_gets_403(): void
    {
        $this->actingAs($this->adminProduction)->get(route('admin.users.index'))->assertStatus(403);
    }

    public function test_admin_news_cannot_manage_users_and_gets_403(): void
    {
        $this->actingAs($this->adminNews)->get(route('admin.users.index'))->assertStatus(403);
    }

    public function test_super_admin_can_create_user(): void
    {
        Livewire::actingAs($this->superAdmin)
            ->test(Form::class)
            ->set('name', 'New Administrator')
            ->set('username', 'new_admin')
            ->set('role_id', $this->prodRole->id)
            ->set('status', true)
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->call('save')
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', [
            'username' => 'new_admin',
            'name' => 'New Administrator',
            'role_id' => $this->prodRole->id,
            'status' => 1,
        ]);
    }

    public function test_validation_works(): void
    {
        Livewire::actingAs($this->superAdmin)
            ->test(Form::class)
            ->set('name', '')
            ->set('username', '')
            ->set('password', '')
            ->call('save')
            ->assertHasErrors(['name', 'username', 'password']);
    }

    public function test_username_uniqueness_works(): void
    {
        Livewire::actingAs($this->superAdmin)
            ->test(Form::class)
            ->set('username', $this->superAdmin->username)
            ->call('save')
            ->assertHasErrors(['username' => 'unique']);
    }

    public function test_password_is_hashed(): void
    {
        Livewire::actingAs($this->superAdmin)
            ->test(Form::class)
            ->set('name', 'Hashed User')
            ->set('username', 'hashed_user')
            ->set('role_id', $this->newsRole->id)
            ->set('status', true)
            ->set('password', 'secretpassword123')
            ->set('password_confirmation', 'secretpassword123')
            ->call('save');

        $user = User::where('username', 'hashed_user')->first();
        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('secretpassword123', $user->password));
    }

    public function test_super_admin_can_edit_user(): void
    {
        $user = User::factory()->create([
            'username' => 'user_edit_test',
            'name' => 'Nama Lama',
            'role_id' => $this->prodRole->id,
            'status' => 1,
        ]);

        Livewire::actingAs($this->superAdmin)
            ->test(Form::class, ['user' => $user])
            ->set('name', 'Nama Baru Terupdate')
            ->set('status', false)
            ->call('save')
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Nama Baru Terupdate',
            'status' => 0,
        ]);
    }

    public function test_empty_password_does_not_change_password_on_edit(): void
    {
        $originalHash = Hash::make('originalpassword123');

        $user = User::factory()->create([
            'username' => 'password_keep_test',
            'password' => $originalHash,
            'role_id' => $this->prodRole->id,
        ]);

        Livewire::actingAs($this->superAdmin)
            ->test(Form::class, ['user' => $user])
            ->set('name', 'Nama Diubah Tanpa Password')
            ->set('password', '')
            ->set('password_confirmation', '')
            ->call('save')
            ->assertRedirect(route('admin.users.index'));

        $user->refresh();
        $this->assertTrue(Hash::check('originalpassword123', $user->password));
    }

    public function test_password_can_be_changed(): void
    {
        $user = User::factory()->create([
            'username' => 'password_change_test',
            'password' => Hash::make('oldpassword123'),
            'role_id' => $this->prodRole->id,
        ]);

        Livewire::actingAs($this->superAdmin)
            ->test(Form::class, ['user' => $user])
            ->set('password', 'newsecretpassword999')
            ->set('password_confirmation', 'newsecretpassword999')
            ->call('save')
            ->assertRedirect(route('admin.users.index'));

        $user->refresh();
        $this->assertTrue(Hash::check('newsecretpassword999', $user->password));
    }

    public function test_role_assignment_works(): void
    {
        $user = User::factory()->create([
            'username' => 'role_assign_test',
            'role_id' => $this->prodRole->id,
        ]);

        Livewire::actingAs($this->superAdmin)
            ->test(Form::class, ['user' => $user])
            ->set('role_id', $this->newsRole->id)
            ->call('save')
            ->assertRedirect(route('admin.users.index'));

        $user->refresh();
        $this->assertEquals($this->newsRole->id, $user->role_id);
    }

    public function test_status_change_works(): void
    {
        $user = User::factory()->create([
            'username' => 'status_change_test',
            'status' => 1,
            'role_id' => $this->prodRole->id,
        ]);

        Livewire::actingAs($this->superAdmin)
            ->test(Form::class, ['user' => $user])
            ->set('status', false)
            ->call('save')
            ->assertRedirect(route('admin.users.index'));

        $user->refresh();
        $this->assertFalse((bool) $user->status);
    }

    public function test_search_works(): void
    {
        $user = User::factory()->create([
            'name' => 'Budi Sudarsono Unik',
            'username' => 'budisudarsono99',
            'role_id' => $this->prodRole->id,
            'status' => 1,
        ]);

        Livewire::actingAs($this->superAdmin)
            ->test(Index::class)
            ->set('search', 'Sudarsono Unik')
            ->assertSee('Budi Sudarsono Unik')
            ->set('search', 'KataKunciTidakAdaSamaSekali')
            ->assertDontSee('Budi Sudarsono Unik');
    }

    public function test_role_filter_works(): void
    {
        $userProd = User::factory()->create([
            'name' => 'User Filter Prod',
            'username' => 'filter_prod',
            'role_id' => $this->prodRole->id,
            'status' => 1,
        ]);

        $userNews = User::factory()->create([
            'name' => 'User Filter News',
            'username' => 'filter_news',
            'role_id' => $this->newsRole->id,
            'status' => 1,
        ]);

        Livewire::actingAs($this->superAdmin)
            ->test(Index::class)
            ->set('roleFilter', (string) $this->newsRole->id)
            ->assertSee('User Filter News')
            ->assertDontSee('User Filter Prod');
    }

    public function test_status_filter_works(): void
    {
        $userActive = User::factory()->create([
            'name' => 'User Status Aktif',
            'username' => 'user_stat_active',
            'status' => 1,
            'role_id' => $this->prodRole->id,
        ]);

        $userInactive = User::factory()->create([
            'name' => 'User Status Inaktif',
            'username' => 'user_stat_inactive',
            'status' => 0,
            'role_id' => $this->prodRole->id,
        ]);

        Livewire::actingAs($this->superAdmin)
            ->test(Index::class)
            ->set('statusFilter', '0')
            ->assertSee('User Status Inaktif')
            ->assertDontSee('User Status Aktif');
    }

    public function test_sorting_works(): void
    {
        Livewire::actingAs($this->superAdmin)
            ->test(Index::class)
            ->call('sortBy', 'username')
            ->assertSet('sortField', 'username')
            ->assertSet('sortDirection', 'asc');
    }

    public function test_pagination_works(): void
    {
        Livewire::actingAs($this->superAdmin)
            ->test(Index::class)
            ->set('perPage', 25)
            ->assertSet('perPage', 25);
    }

    public function test_unauthorized_direct_url_returns_403(): void
    {
        $this->actingAs($this->adminProduction)->get(route('admin.users.index'))->assertStatus(403);
        $this->actingAs($this->adminProduction)->get(route('admin.users.create'))->assertStatus(403);
        $this->actingAs($this->adminNews)->get(route('admin.users.index'))->assertStatus(403);
        $this->actingAs($this->adminNews)->get(route('admin.users.create'))->assertStatus(403);
    }

    public function test_unauthorized_livewire_mutation_returns_403(): void
    {
        $user = User::factory()->create(['role_id' => $this->prodRole->id]);

        $this->actingAs($this->adminNews)->get(route('admin.users.edit', $user))->assertStatus(403);
        $this->actingAs($this->adminProduction)->get(route('admin.users.edit', $user))->assertStatus(403);
    }

    public function test_cannot_delete_current_user(): void
    {
        Livewire::actingAs($this->superAdmin)
            ->test(Index::class)
            ->call('delete', $this->superAdmin->id)
            ->assertSee('Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif.');

        $this->assertDatabaseHas('users', ['id' => $this->superAdmin->id]);
    }

    public function test_cannot_remove_last_super_admin(): void
    {
        // Keep only 1 Super Admin active
        User::where('role_id', $this->superAdminRole->id)
            ->where('id', '!=', $this->superAdmin->id)
            ->delete();

        // Create a secondary Super Admin that logs in
        $otherSuperAdmin = User::factory()->create([
            'role_id' => $this->superAdminRole->id,
            'status' => 1,
        ]);

        // Attempt to demote or deactivate $otherSuperAdmin when $otherSuperAdmin is the only one remaining
        $this->superAdmin->delete();

        Livewire::actingAs($otherSuperAdmin)
            ->test(Form::class, ['user' => $otherSuperAdmin])
            ->set('role_id', $this->prodRole->id)
            ->call('save')
            ->assertHasErrors(['status']);
    }

    public function test_user_relationship_works(): void
    {
        $this->assertEquals('Super Admin', $this->superAdmin->role->name);
    }

    public function test_role_relationship_works(): void
    {
        $this->assertTrue($this->superAdminRole->users()->count() > 0);
    }
}
