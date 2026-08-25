<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Banners\Form;
use App\Livewire\Admin\Banners\Index;
use App\Models\Banner;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class BannerManagementTest extends TestCase
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

    public function test_unauthenticated_user_cannot_access_banners_index(): void
    {
        $response = $this->get(route('admin.banners.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_inactive_user_cannot_access_banners_index(): void
    {
        $response = $this->actingAs($this->inactiveUser)->get(route('admin.banners.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_super_admin_can_view_banners_index_and_sees_existing_banners(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('admin.banners.index'));
        $response->assertStatus(200);
        $response->assertSee('Daftar Banner Promosi');
        $response->assertSee('Tambah Banner');
    }

    public function test_admin_production_can_view_banners_index_and_sees_create_button(): void
    {
        $response = $this->actingAs($this->adminProduction)->get(route('admin.banners.index'));
        $response->assertStatus(200);
        $response->assertSee('Daftar Banner Promosi');
        $response->assertSee('Tambah Banner');
    }

    public function test_admin_news_can_view_banners_index_but_cannot_see_create_button(): void
    {
        $response = $this->actingAs($this->adminNews)->get(route('admin.banners.index'));
        $response->assertStatus(200);
        $response->assertSee('Daftar Banner Promosi');
        $response->assertDontSee('Tambah Banner');
    }

    public function test_admin_news_does_not_see_edit_or_delete_buttons(): void
    {
        Livewire::actingAs($this->adminNews)
            ->test(Index::class)
            ->assertDontSeeHtml('wire:click="delete(')
            ->assertDontSeeHtml('title="Edit Banner"');
    }

    public function test_super_admin_can_open_create_form(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('admin.banners.create'));
        $response->assertStatus(200);
        $response->assertSee('Formulir Tambah Banner Baru');
    }

    public function test_admin_production_can_open_create_form(): void
    {
        $response = $this->actingAs($this->adminProduction)->get(route('admin.banners.create'));
        $response->assertStatus(200);
        $response->assertSee('Formulir Tambah Banner Baru');
    }

    public function test_admin_news_cannot_open_create_form_and_gets_403(): void
    {
        $response = $this->actingAs($this->adminNews)->get(route('admin.banners.create'));
        $response->assertStatus(403);
    }

    public function test_create_banner_success(): void
    {
        $file = UploadedFile::fake()->image('promo.jpg', 1200, 400);

        Livewire::actingAs($this->adminProduction)
            ->test(Form::class)
            ->set('title', 'Banner Festival Kampus 2026')
            ->set('content', 'Deskripsi acara festival kampus WPiTV')
            ->set('image', $file)
            ->call('save')
            ->assertRedirect(route('admin.banners.index'));

        $this->assertDatabaseHas('ms_banner', [
            'title' => 'Banner Festival Kampus 2026',
            'content' => 'Deskripsi acara festival kampus WPiTV',
        ]);

        $created = Banner::where('title', 'Banner Festival Kampus 2026')->first();
        $this->assertNotNull($created);
        $this->assertNotNull($created->image);
        Storage::disk('public')->assertExists('banners/'.$created->image);
    }

    public function test_edit_banner_success(): void
    {
        $banner = Banner::create([
            'title' => 'Banner Awal',
            'content' => 'Konten awal',
        ]);

        Livewire::actingAs($this->superAdmin)
            ->test(Form::class, ['banner' => $banner])
            ->set('title', 'Banner Diperbarui')
            ->set('content', 'Konten diperbarui')
            ->call('save')
            ->assertRedirect(route('admin.banners.index'));

        $this->assertDatabaseHas('ms_banner', [
            'id' => $banner->id,
            'title' => 'Banner Diperbarui',
            'content' => 'Konten diperbarui',
        ]);
    }

    public function test_delete_by_super_admin_success(): void
    {
        $banner = Banner::create([
            'title' => 'Banner Untuk Dihapus Super Admin',
        ]);

        Livewire::actingAs($this->superAdmin)
            ->test(Index::class)
            ->call('delete', $banner->id);

        $this->assertDatabaseMissing('ms_banner', [
            'id' => $banner->id,
        ]);
    }

    public function test_delete_by_admin_production_success(): void
    {
        $banner = Banner::create([
            'title' => 'Banner Untuk Dihapus Production',
        ]);

        Livewire::actingAs($this->adminProduction)
            ->test(Index::class)
            ->call('delete', $banner->id);

        $this->assertDatabaseMissing('ms_banner', [
            'id' => $banner->id,
        ]);
    }

    public function test_delete_by_admin_news_is_denied_with_403(): void
    {
        $banner = Banner::create([
            'title' => 'Banner Dilarang Dihapus News',
        ]);

        Livewire::actingAs($this->adminNews)
            ->test(Index::class)
            ->call('delete', $banner->id)
            ->assertStatus(403);

        $this->assertDatabaseHas('ms_banner', [
            'id' => $banner->id,
        ]);
    }

    public function test_search_by_title_and_content(): void
    {
        $banner = Banner::create([
            'title' => 'Banner Unik Khusus Search',
            'content' => 'KontenSpesifikPencarian',
        ]);

        Livewire::actingAs($this->superAdmin)
            ->test(Index::class)
            ->set('search', 'Unik Khusus')
            ->assertSee('Banner Unik Khusus Search')
            ->set('search', 'KontenSpesifikPencarian')
            ->assertSee('Banner Unik Khusus Search')
            ->set('search', 'KataKunciTidakAda')
            ->assertDontSee('Banner Unik Khusus Search');
    }

    public function test_legacy_banner_with_missing_image_uses_fallback_placeholder(): void
    {
        $legacyBanner = Banner::where('image', '!=', '')->firstOrFail();
        $this->assertStringContainsString('program-placeholder.svg', $legacyBanner->image_url);
    }

    public function test_image_upload_and_replacement_cleans_up_old_storage_file(): void
    {
        $file1 = UploadedFile::fake()->image('banner1.jpg', 1200, 400);
        $file2 = UploadedFile::fake()->image('banner2.jpg', 1200, 400);

        // 1. Create with file1
        Livewire::actingAs($this->superAdmin)
            ->test(Form::class)
            ->set('title', 'Banner Ganti Gambar')
            ->set('image', $file1)
            ->call('save');

        $banner = Banner::where('title', 'Banner Ganti Gambar')->firstOrFail();
        $oldImageName = $banner->image;
        Storage::disk('public')->assertExists('banners/'.$oldImageName);

        // 2. Edit with file2
        Livewire::actingAs($this->superAdmin)
            ->test(Form::class, ['banner' => $banner])
            ->set('image', $file2)
            ->call('save');

        $banner->refresh();
        $newImageName = $banner->image;

        $this->assertNotEquals($oldImageName, $newImageName);
        Storage::disk('public')->assertExists('banners/'.$newImageName);
        Storage::disk('public')->assertMissing('banners/'.$oldImageName); // Old cleaned up!
    }
}
