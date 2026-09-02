<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Videos\Form;
use App\Livewire\Admin\Videos\Index;
use App\Models\Program;
use App\Models\Role;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class VideoManagementTest extends TestCase
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

    public function test_unauthenticated_user_cannot_access_videos_index(): void
    {
        $response = $this->get(route('admin.videos.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_inactive_user_cannot_access_videos_index(): void
    {
        $response = $this->actingAs($this->inactiveUser)->get(route('admin.videos.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_super_admin_can_view_videos_index_and_sees_existing_videos(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('admin.videos.index'));
        $response->assertStatus(200);
        $response->assertSee('Daftar Tayangan Video');
        $response->assertSee('Tambah Tayangan');
    }

    public function test_admin_production_can_view_videos_index(): void
    {
        $response = $this->actingAs($this->adminProduction)->get(route('admin.videos.index'));
        $response->assertStatus(200);
        $response->assertSee('Daftar Tayangan Video');
        $response->assertSee('Tambah Tayangan');
    }

    public function test_admin_news_can_view_videos_index_and_sees_create_button(): void
    {
        $response = $this->actingAs($this->adminNews)->get(route('admin.videos.index'));
        $response->assertStatus(200);
        $response->assertSee('Daftar Tayangan Video');
        $response->assertSee('Tambah Tayangan');
    }

    public function test_admin_news_does_not_see_delete_button(): void
    {
        $video = Video::create(['title' => 'Video Uji Hak Akses', 'youtube_id' => 'testVideo01', 'air_date' => now()->toDateString()]);
        Livewire::actingAs($this->adminNews)
            ->test(Index::class)
            ->assertDontSeeHtml('wire:click="delete('.$video->id.')"');
    }

    public function test_super_admin_can_open_create_form(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('admin.videos.create'));
        $response->assertStatus(200);
        $response->assertSee('Formulir Tambah Tayangan Baru');
    }

    public function test_admin_production_can_open_create_form(): void
    {
        $response = $this->actingAs($this->adminProduction)->get(route('admin.videos.create'));
        $response->assertStatus(200);
        $response->assertSee('Formulir Tambah Tayangan Baru');
    }

    public function test_admin_news_can_open_create_form(): void
    {
        $response = $this->actingAs($this->adminNews)->get(route('admin.videos.create'));
        $response->assertStatus(200);
        $response->assertSee('Formulir Tambah Tayangan Baru');
    }

    public function test_youtube_watch_url_is_extracted_correctly(): void
    {
        $id = Form::extractYouTubeId('https://www.youtube.com/watch?v=dQw4w9WgXcQ');
        $this->assertEquals('dQw4w9WgXcQ', $id);
    }

    public function test_youtube_short_url_is_extracted_correctly(): void
    {
        $id = Form::extractYouTubeId('https://youtu.be/dQw4w9WgXcQ?si=12345');
        $this->assertEquals('dQw4w9WgXcQ', $id);
    }

    public function test_youtube_embed_url_is_extracted_correctly(): void
    {
        $id = Form::extractYouTubeId('https://www.youtube.com/embed/dQw4w9WgXcQ');
        $this->assertEquals('dQw4w9WgXcQ', $id);
    }

    public function test_direct_youtube_id_is_processed_correctly(): void
    {
        $id = Form::extractYouTubeId('dQw4w9WgXcQ');
        $this->assertEquals('dQw4w9WgXcQ', $id);
    }

    public function test_create_video_success_and_sets_auth_user_id(): void
    {
        $program = Program::create(['name' => 'Program Uji', 'slug' => 'program-uji', 'status' => 1]);

        Livewire::actingAs($this->superAdmin)
            ->test(Form::class)
            ->set('program_id', $program->id)
            ->set('title', 'Video Pengujian Baru')
            ->set('youtube_input', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ')
            ->set('air_date', '2026-08-17')
            ->set('description', 'Deskripsi pengujian')
            ->set('tag', '0')
            ->call('save')
            ->assertRedirect(route('admin.videos.index'));

        $this->assertDatabaseHas('ms_video', [
            'title' => 'Video Pengujian Baru',
            'youtube_id' => 'dQw4w9WgXcQ',
            'user_id' => $this->superAdmin->id,
            'program_id' => $program->id,
        ]);
    }

    public function test_duplicate_youtube_id_can_be_created_without_unique_error(): void
    {
        // Existing duplicate ID in database: GYQWMuQISdI
        Livewire::actingAs($this->adminProduction)
            ->test(Form::class)
            ->set('title', 'Video Duplikat Baru')
            ->set('youtube_input', 'GYQWMuQISdI')
            ->set('air_date', '2026-08-17')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.videos.index'));

        $this->assertDatabaseHas('ms_video', [
            'title' => 'Video Duplikat Baru',
            'youtube_id' => 'GYQWMuQISdI',
        ]);
    }

    public function test_edit_video_success_and_preserves_original_user_id(): void
    {
        $originalUploader = User::factory()->create(['status' => 1]);
        $video = Video::create([
            'title' => 'Video Awal',
            'youtube_id' => 'dQw4w9WgXcQ',
            'air_date' => '2026-08-01',
            'user_id' => $originalUploader->id,
        ]);

        // Super Admin edits the video
        Livewire::actingAs($this->superAdmin)
            ->test(Form::class, ['video' => $video])
            ->set('title', 'Video Judul Diperbarui')
            ->call('save')
            ->assertRedirect(route('admin.videos.index'));

        $this->assertDatabaseHas('ms_video', [
            'id' => $video->id,
            'title' => 'Video Judul Diperbarui',
            'user_id' => $originalUploader->id, // Preserved!
        ]);
    }

    public function test_delete_by_super_admin_success(): void
    {
        $video = Video::create([
            'title' => 'Video Untuk Dihapus',
            'youtube_id' => 'dQw4w9WgXcQ',
            'air_date' => '2026-08-10',
        ]);

        Livewire::actingAs($this->superAdmin)
            ->test(Index::class)
            ->call('delete', $video->id);

        $this->assertDatabaseMissing('ms_video', [
            'id' => $video->id,
        ]);
    }

    public function test_delete_by_admin_production_success(): void
    {
        $video = Video::create([
            'title' => 'Video Untuk Dihapus Production',
            'youtube_id' => 'dQw4w9WgXcQ',
            'air_date' => '2026-08-10',
        ]);

        Livewire::actingAs($this->adminProduction)
            ->test(Index::class)
            ->call('delete', $video->id);

        $this->assertDatabaseMissing('ms_video', [
            'id' => $video->id,
        ]);
    }

    public function test_delete_by_admin_news_is_denied_with_403(): void
    {
        $video = Video::create([
            'title' => 'Video Untuk Dihapus News',
            'youtube_id' => 'dQw4w9WgXcQ',
            'air_date' => '2026-08-10',
        ]);

        Livewire::actingAs($this->adminNews)
            ->test(Index::class)
            ->call('delete', $video->id)
            ->assertStatus(403);

        $this->assertDatabaseHas('ms_video', [
            'id' => $video->id,
        ]);
    }

    public function test_search_by_title_and_youtube_id(): void
    {
        $video = Video::create([
            'title' => 'Video Spesifik Unik Sekali',
            'youtube_id' => 'xXxXxXxXxXx',
            'air_date' => '2026-08-10',
        ]);

        Livewire::actingAs($this->superAdmin)
            ->test(Index::class)
            ->set('search', 'Unik Sekali')
            ->assertSee('Video Spesifik Unik Sekali')
            ->set('search', 'xXxXxXxXxXx')
            ->assertSee('Video Spesifik Unik Sekali')
            ->set('search', 'TidakAdaDiManaMana')
            ->assertDontSee('Video Spesifik Unik Sekali');
    }

    public function test_filter_by_program(): void
    {
        $programA = Program::create(['name' => 'Program Uji A', 'slug' => 'program-uji-a', 'status' => 1]);
        $programB = Program::create(['name' => 'Program Uji B', 'slug' => 'program-uji-b', 'status' => 1]);

        $videoA = Video::create([
            'title' => 'Video Program A',
            'program_id' => $programA->id,
            'youtube_id' => 'dQw4w9WgXcA',
            'air_date' => '2026-08-10',
        ]);

        $videoB = Video::create([
            'title' => 'Video Program B',
            'program_id' => $programB->id,
            'youtube_id' => 'dQw4w9WgXcB',
            'air_date' => '2026-08-10',
        ]);

        Livewire::actingAs($this->superAdmin)
            ->test(Index::class)
            ->set('programId', (string) $programA->id)
            ->assertSee('Video Program A')
            ->assertDontSee('Video Program B');
    }

    public function test_video_with_null_program_and_null_user_displays_correctly(): void
    {
        Video::create(['title' => 'Video Uji Tanpa Relasi', 'program_id' => null, 'user_id' => null, 'youtube_id' => 'testVideo01', 'air_date' => now()->toDateString()]);
        $response = $this->actingAs($this->superAdmin)->get(route('admin.videos.index'));
        $response->assertStatus(200);
        $response->assertSee('Tanpa Program');
        $response->assertSee('Legacy / System');
    }

    public function test_thumbnail_url_accessor(): void
    {
        $video = new Video(['youtube_id' => 'em3BtXqAgEA']);
        $this->assertEquals('https://img.youtube.com/vi/em3BtXqAgEA/hqdefault.jpg', $video->thumbnail_url);
    }
}
