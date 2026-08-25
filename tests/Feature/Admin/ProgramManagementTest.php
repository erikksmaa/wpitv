<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Programs\Form;
use App\Livewire\Admin\Programs\Index;
use App\Models\Program;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ProgramManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected User $superAdmin;

    protected User $adminProduction;

    protected User $adminNews;

    protected User $inactiveUser;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $superAdminRole = Role::where('name', 'Super Admin')->first() ?? Role::find(1);
        $prodRole = Role::where('name', 'Admin Production')->first() ?? Role::find(2);
        $newsRole = Role::where('name', 'Admin News')->first() ?? Role::find(3);

        $this->superAdmin = User::factory()->create([
            'role_id' => $superAdminRole->id,
            'status' => 1,
        ]);

        $this->adminProduction = User::factory()->create([
            'role_id' => $prodRole->id,
            'status' => 1,
        ]);

        $this->adminNews = User::factory()->create([
            'role_id' => $newsRole->id,
            'status' => 1,
        ]);

        $this->inactiveUser = User::factory()->create([
            'role_id' => $superAdminRole->id,
            'status' => 0,
        ]);
    }

    public function test_unauthenticated_user_cannot_access_programs_index(): void
    {
        $response = $this->get(route('admin.programs.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_inactive_user_cannot_access_programs_index(): void
    {
        $response = $this->actingAs($this->inactiveUser)->get(route('admin.programs.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_super_admin_can_view_programs_index_and_sees_existing_programs(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('admin.programs.index'));
        $response->assertStatus(200);
        $response->assertSee('AKTUAL');
        $response->assertSee('Tambah Program');
    }

    public function test_admin_production_can_view_programs_index(): void
    {
        $response = $this->actingAs($this->adminProduction)->get(route('admin.programs.index'));
        $response->assertStatus(200);
        $response->assertSee('Daftar Program Televisi');
        $response->assertSee('Tambah Program');
    }

    public function test_admin_news_can_view_programs_index_but_cannot_see_create_button(): void
    {
        $response = $this->actingAs($this->adminNews)->get(route('admin.programs.index'));
        $response->assertStatus(200);
        $response->assertSee('Daftar Program Televisi');
        $response->assertDontSee('Tambah Program');
    }

    public function test_search_by_name_and_slug_works(): void
    {
        Livewire::actingAs($this->superAdmin)
            ->test(Index::class)
            ->set('search', 'AKTUAL')
            ->assertSee('AKTUAL')
            ->assertDontSee('PODCAST PASTI');
    }

    public function test_filter_by_status_works(): void
    {
        $inactiveProg = Program::create([
            'name' => 'Program Test Inaktif',
            'slug' => 'program-test-inaktif',
            'status' => 0,
        ]);

        Livewire::actingAs($this->superAdmin)
            ->test(Index::class)
            ->set('status', '0')
            ->assertSee('Program Test Inaktif')
            ->set('status', '1')
            ->assertDontSee('Program Test Inaktif');
    }

    public function test_super_admin_can_access_create_form(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('admin.programs.create'));
        $response->assertStatus(200);
        $response->assertSee('Formulir Program Baru');
    }

    public function test_admin_news_cannot_access_create_form_and_gets_403(): void
    {
        $response = $this->actingAs($this->adminNews)->get(route('admin.programs.create'));
        $response->assertStatus(403);
    }

    public function test_program_can_be_created_with_auto_slug_and_image_upload(): void
    {
        $file = UploadedFile::fake()->image('cover.jpg', 600, 400);

        Livewire::actingAs($this->superAdmin)
            ->test(Form::class)
            ->set('name', 'Program Baru Juara')
            ->assertSet('slug', 'program-baru-juara')
            ->set('description', 'Deskripsi program baru untuk testing')
            ->set('status', true)
            ->set('image', $file)
            ->call('save')
            ->assertRedirect(route('admin.programs.index'));

        $this->assertDatabaseHas('ms_program', [
            'name' => 'Program Baru Juara',
            'slug' => 'program-baru-juara',
            'status' => 1,
        ]);

        $program = Program::where('slug', 'program-baru-juara')->first();
        $this->assertNotNull($program);
        $this->assertNotNull($program->image);
        Storage::disk('public')->assertExists('programs/'.$program->image);
    }

    public function test_slug_uniqueness_validation(): void
    {
        Livewire::actingAs($this->superAdmin)
            ->test(Form::class)
            ->set('name', 'Aktual Duplikat')
            ->set('slug', 'aktual') // 'aktual' already exists in ms_program (ID 80)
            ->call('save')
            ->assertHasErrors(['slug' => 'unique']);
    }

    public function test_program_can_be_edited_and_updated(): void
    {
        $program = Program::where('slug', 'aktual')->firstOrFail();

        Livewire::actingAs($this->superAdmin)
            ->test(Form::class, ['program' => $program])
            ->assertSet('name', $program->name)
            ->assertSet('slug', $program->slug)
            ->set('description', 'Deskripsi diperbarui untuk testing')
            ->call('save')
            ->assertRedirect(route('admin.programs.index'));

        $this->assertDatabaseHas('ms_program', [
            'id' => $program->id,
            'description' => 'Deskripsi diperbarui untuk testing',
        ]);
    }

    public function test_delete_is_blocked_when_program_has_videos(): void
    {
        // Program ID 80 (AKTUAL) has 264 existing videos
        $program = Program::withCount('videos')->findOrFail(80);
        $this->assertGreaterThan(0, $program->videos_count);

        Livewire::actingAs($this->superAdmin)
            ->test(Index::class)
            ->call('delete', 80)
            ->assertSee('tidak dapat dihapus karena masih memiliki data terkait');

        $this->assertDatabaseHas('ms_program', [
            'id' => 80,
        ]);
    }

    public function test_program_without_dependency_can_be_deleted(): void
    {
        $standaloneProg = Program::create([
            'name' => 'Program Tanpa Relasi',
            'slug' => 'program-tanpa-relasi',
            'status' => 1,
        ]);

        Livewire::actingAs($this->superAdmin)
            ->test(Index::class)
            ->call('delete', $standaloneProg->id);

        $this->assertDatabaseMissing('ms_program', [
            'id' => $standaloneProg->id,
        ]);
    }

    public function test_admin_news_cannot_delete_program(): void
    {
        $standaloneProg = Program::create([
            'name' => 'Program Untuk Dites News',
            'slug' => 'program-untuk-dites-news',
            'status' => 1,
        ]);

        Livewire::actingAs($this->adminNews)
            ->test(Index::class)
            ->call('delete', $standaloneProg->id)
            ->assertStatus(403);
    }
}
