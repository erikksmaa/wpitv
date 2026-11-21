<?php

namespace Tests\Feature;

use App\Models\Role;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class TestEnvironmentTest extends TestCase
{
    public function test_database_and_services_resolve_to_isolated_environment(): void
    {
        $this->assertTrue(app()->environment('testing'));
        $this->assertSame('wpitv_rebuild_testing', DB::selectOne('SELECT DATABASE() AS name')->name);
        $this->assertFalse(app()->configurationIsCached());
        $this->assertSame('array', config('cache.default'));
        $this->assertSame('array', config('session.driver'));
        $this->assertSame('array', config('mail.default'));
        $this->assertSame('sync', config('queue.default'));
        $this->assertStringContainsString('storage/app/testing/runtime-', str_replace('\\', '/', storage_path()));
        $this->assertStringNotContainsString('bootstrap/cache/config.php', app()->getCachedConfigPath());
    }

    public function test_synthetic_rows_and_uploads_remain_in_disposable_resources(): void
    {
        $role = Role::create(['name' => 'CANARY_'.bin2hex(random_bytes(8)), 'description' => 'Synthetic canary']);
        $this->assertDatabaseHas('roles', ['id' => $role->id]);
        Storage::disk('public')->put('canary.txt', 'synthetic');
        Storage::disk('public')->assertExists('canary.txt');
        $this->assertStringStartsWith(storage_path(), Storage::disk('public')->path('canary.txt'));
        $this->assertFalse(is_file(base_path('storage/app/public/canary.txt')));
    }

    public function test_livewire_temporary_uploads_use_private_non_executable_storage(): void
    {
        $this->assertSame('local', config('livewire.temporary_file_upload.disk'));
        $this->assertSame('livewire-tmp', config('livewire.temporary_file_upload.directory'));
        $this->assertContains('mimes:jpg,jpeg,png,webp', config('livewire.temporary_file_upload.rules'));

        Storage::disk('local')->put('livewire-tmp/payload.php', '<?php echo "unsafe";');
        $privatePath = str_replace('\\', '/', Storage::disk('local')->path('livewire-tmp/payload.php'));

        $this->assertStringNotContainsString('/public/', $privatePath);
        $this->assertContains($this->get('/storage/livewire-tmp/payload.php')->status(), [403, 404]);

        $rules = config('livewire.temporary_file_upload.rules');
        $this->assertTrue(Validator::make([
            'upload' => UploadedFile::fake()->createWithContent('payload.php', '<?php echo "unsafe";'),
        ], ['upload' => $rules])->fails());
        $this->assertFalse(Validator::make([
            'upload' => UploadedFile::fake()->image('safe.jpg', 100, 100),
        ], ['upload' => $rules])->fails());
    }
}
