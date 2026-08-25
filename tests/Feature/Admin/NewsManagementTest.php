<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\News\Form;
use App\Livewire\Admin\News\Index;
use App\Models\News;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class NewsManagementTest extends TestCase
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

    public function test_unauthenticated_user_cannot_access_news_index(): void
    {
        $response = $this->get(route('admin.news.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_inactive_user_cannot_access_news_index(): void
    {
        $response = $this->actingAs($this->inactiveUser)->get(route('admin.news.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_super_admin_can_view_news_index_and_sees_existing_news(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('admin.news.index'));
        $response->assertStatus(200);
        $response->assertSee('Daftar Artikel Berita');
        $response->assertSee('Tambah Berita');
    }

    public function test_admin_news_can_view_news_index_and_sees_create_button(): void
    {
        $response = $this->actingAs($this->adminNews)->get(route('admin.news.index'));
        $response->assertStatus(200);
        $response->assertSee('Daftar Artikel Berita');
        $response->assertSee('Tambah Berita');
    }

    public function test_admin_production_can_view_news_index_but_cannot_see_create_button(): void
    {
        $response = $this->actingAs($this->adminProduction)->get(route('admin.news.index'));
        $response->assertStatus(200);
        $response->assertSee('Daftar Artikel Berita');
        $response->assertDontSee('Tambah Berita');
    }

    public function test_admin_production_does_not_see_edit_or_delete_buttons(): void
    {
        Livewire::actingAs($this->adminProduction)
            ->test(Index::class)
            ->assertDontSeeHtml('wire:click="delete(')
            ->assertDontSeeHtml('title="Edit Berita"');
    }

    public function test_super_admin_can_open_create_form(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('admin.news.create'));
        $response->assertStatus(200);
        $response->assertSee('Formulir Tambah Berita Baru');
    }

    public function test_admin_news_can_open_create_form(): void
    {
        $response = $this->actingAs($this->adminNews)->get(route('admin.news.create'));
        $response->assertStatus(200);
        $response->assertSee('Formulir Tambah Berita Baru');
    }

    public function test_admin_production_cannot_open_create_form_and_gets_403(): void
    {
        $response = $this->actingAs($this->adminProduction)->get(route('admin.news.create'));
        $response->assertStatus(403);
    }

    public function test_create_news_success_and_auto_sets_user_id_and_author_name(): void
    {
        $file = UploadedFile::fake()->image('headline.jpg', 800, 600);

        Livewire::actingAs($this->adminNews)
            ->test(Form::class)
            ->set('title', 'Mahasiswa WPiTV Raih Juara 1 Nasional')
            ->assertSet('slug', 'mahasiswa-wpitv-raih-juara-1-nasional')
            ->assertSet('author_name', $this->adminNews->name)
            ->set('content', '<p>Berita lengkap prestasi membanggakan mahasiswa.</p>')
            ->set('image', $file)
            ->call('save')
            ->assertRedirect(route('admin.news.index'));

        $this->assertDatabaseHas('ms_news', [
            'title' => 'Mahasiswa WPiTV Raih Juara 1 Nasional',
            'slug' => 'mahasiswa-wpitv-raih-juara-1-nasional',
            'user_id' => $this->adminNews->id,
            'author_name' => $this->adminNews->name,
        ]);

        $created = News::where('slug', 'mahasiswa-wpitv-raih-juara-1-nasional')->first();
        $this->assertNotNull($created);
        $this->assertNotNull($created->image);
        Storage::disk('public')->assertExists('news/'.$created->image);
    }

    public function test_duplicate_slug_is_rejected_by_validation(): void
    {
        $existing = News::first();

        Livewire::actingAs($this->adminNews)
            ->test(Form::class)
            ->set('title', 'Judul Baru Tapi Slug Duplikat')
            ->set('slug', $existing->slug)
            ->set('content', 'Konten berita')
            ->call('save')
            ->assertHasErrors(['slug' => 'unique']);
    }

    public function test_edit_news_success_and_preserves_original_user_id(): void
    {
        $originalUploader = User::factory()->create(['status' => 1]);
        $news = News::create([
            'title' => 'Berita Baru Uploader Asli',
            'slug' => 'berita-baru-uploader-asli',
            'content' => '<p>Konten awal</p>',
            'author_name' => 'Penulis Asli',
            'user_id' => $originalUploader->id,
        ]);

        // Super Admin edits the news
        Livewire::actingAs($this->superAdmin)
            ->test(Form::class, ['news' => $news])
            ->set('title', 'Berita Baru Uploader Asli (Diperbarui)')
            ->set('content', '<p>Konten diperbarui</p>')
            ->call('save')
            ->assertRedirect(route('admin.news.index'));

        $this->assertDatabaseHas('ms_news', [
            'id' => $news->id,
            'title' => 'Berita Baru Uploader Asli (Diperbarui)',
            'user_id' => $originalUploader->id, // Preserved!
        ]);
    }

    public function test_edit_news_preserves_null_user_id_on_legacy_records(): void
    {
        // Legacy record (ID 265) has user_id = null
        $legacyNews = News::whereNull('user_id')->firstOrFail();

        Livewire::actingAs($this->adminNews)
            ->test(Form::class, ['news' => $legacyNews])
            ->set('author_name', '@ddstya_updated')
            ->call('save')
            ->assertRedirect(route('admin.news.index'));

        $this->assertDatabaseHas('ms_news', [
            'id' => $legacyNews->id,
            'author_name' => '@ddstya_updated',
            'user_id' => null, // Preserved null!
        ]);
    }

    public function test_delete_by_super_admin_success(): void
    {
        $news = News::create([
            'title' => 'Berita Untuk Dihapus Super Admin',
            'slug' => 'berita-untuk-dihapus-super-admin',
            'content' => '<p>Konten</p>',
        ]);

        Livewire::actingAs($this->superAdmin)
            ->test(Index::class)
            ->call('delete', $news->id);

        $this->assertDatabaseMissing('ms_news', [
            'id' => $news->id,
        ]);
    }

    public function test_delete_by_admin_news_success(): void
    {
        $news = News::create([
            'title' => 'Berita Untuk Dihapus Admin News',
            'slug' => 'berita-untuk-dihapus-admin-news',
            'content' => '<p>Konten</p>',
        ]);

        Livewire::actingAs($this->adminNews)
            ->test(Index::class)
            ->call('delete', $news->id);

        $this->assertDatabaseMissing('ms_news', [
            'id' => $news->id,
        ]);
    }

    public function test_delete_by_admin_production_is_denied_with_403(): void
    {
        $news = News::create([
            'title' => 'Berita Dilarang Dihapus Production',
            'slug' => 'berita-dilarang-dihapus-production',
            'content' => '<p>Konten</p>',
        ]);

        Livewire::actingAs($this->adminProduction)
            ->test(Index::class)
            ->call('delete', $news->id)
            ->assertStatus(403);

        $this->assertDatabaseHas('ms_news', [
            'id' => $news->id,
        ]);
    }

    public function test_search_by_title_slug_and_author_name(): void
    {
        $news = News::create([
            'title' => 'Kucing Anggora Lucu Sekali',
            'slug' => 'kucing-anggora-lucu-sekali-unik',
            'author_name' => 'WartawanKucing',
            'content' => '<p>Konten</p>',
        ]);

        Livewire::actingAs($this->superAdmin)
            ->test(Index::class)
            ->set('search', 'Kucing Anggora')
            ->assertSee('Kucing Anggora Lucu Sekali')
            ->set('search', 'lucu-sekali-unik')
            ->assertSee('Kucing Anggora Lucu Sekali')
            ->set('search', 'WartawanKucing')
            ->assertSee('Kucing Anggora Lucu Sekali')
            ->set('search', 'KataKunciTidakAda')
            ->assertDontSee('Kucing Anggora Lucu Sekali');
    }

    public function test_filter_by_year(): void
    {
        $news2020 = News::whereYear('created_at', 2020)->latest('created_at')->first();
        if ($news2020) {
            Livewire::actingAs($this->superAdmin)
                ->test(Index::class)
                ->set('year', '2020')
                ->assertSee($news2020->title);
        }
    }

    public function test_legacy_news_with_missing_image_uses_fallback_placeholder(): void
    {
        $legacyNews = News::where('image', '!=', '')->firstOrFail();
        $this->assertStringContainsString('program-placeholder.svg', $legacyNews->image_url);
    }

    public function test_model_accessors_image_url_and_author_display_name(): void
    {
        $newsWithAuthor = new News(['author_name' => 'Kontributor Luar']);
        $this->assertEquals('Kontributor Luar', $newsWithAuthor->author_display_name);

        $newsEmpty = new News([]);
        $this->assertEquals('Redaksi WPiTV', $newsEmpty->author_display_name);
    }

    public function test_image_upload_and_replacement_cleans_up_old_storage_file(): void
    {
        $file1 = UploadedFile::fake()->image('cover1.jpg', 600, 400);
        $file2 = UploadedFile::fake()->image('cover2.jpg', 600, 400);

        // 1. Create with file1
        Livewire::actingAs($this->superAdmin)
            ->test(Form::class)
            ->set('title', 'Berita Ganti Gambar')
            ->set('slug', 'berita-ganti-gambar')
            ->set('content', 'Konten')
            ->set('image', $file1)
            ->call('save');

        $news = News::where('slug', 'berita-ganti-gambar')->firstOrFail();
        $oldImageName = $news->image;
        Storage::disk('public')->assertExists('news/'.$oldImageName);

        // 2. Edit with file2
        Livewire::actingAs($this->superAdmin)
            ->test(Form::class, ['news' => $news])
            ->set('image', $file2)
            ->call('save');

        $news->refresh();
        $newImageName = $news->image;

        $this->assertNotEquals($oldImageName, $newImageName);
        Storage::disk('public')->assertExists('news/'.$newImageName);
        Storage::disk('public')->assertMissing('news/'.$oldImageName); // Old cleaned up!
    }
}
