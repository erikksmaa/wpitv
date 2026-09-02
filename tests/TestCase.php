<?php

namespace Tests;

use App\Http\Middleware\EnsureAdminSessionIsValid;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable as UserContract;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Support\TestEnvironment;

abstract class TestCase extends BaseTestCase
{
    private ?User $syntheticAuthor = null;

    public function actingAs(UserContract $user, $guard = null)
    {
        parent::actingAs($user, $guard);
        $now = now()->timestamp;

        return $this->withSession([
            EnsureAdminSessionIsValid::STARTED_AT => $now,
            EnsureAdminSessionIsValid::LAST_ACTIVITY_AT => $now,
            'auth.password_confirmed_at' => $now,
        ]);
    }

    protected function contentAuthor(): User
    {
        return $this->syntheticAuthor ??= User::factory()->create([
            'name' => 'Penulis Uji',
            'role_id' => Role::where('name', User::ROLE_NEWS)->firstOrFail()->id,
        ]);
    }

    public function createApplication()
    {
        TestEnvironment::initialize();
        $app = require dirname(__DIR__).'/bootstrap/app.php';
        $app->useStoragePath(TestEnvironment::runtime());
        $this->traitsUsedByTest = array_flip(class_uses_recursive(static::class));
        foreach (['RefreshDatabase', 'DatabaseMigrations', 'DatabaseTruncation'] as $trait) {
            if (isset($this->traitsUsedByTest['Illuminate\\Foundation\\Testing\\'.$trait])) {
                throw new \RuntimeException('TEST SAFETY ABORT: migration/truncation traits are not allowed.');
            }
        }
        $app->make(Kernel::class)->bootstrap();
        TestEnvironment::configure($app);

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Http::preventStrayRequests();
        Storage::fake('local');
        Storage::fake('public');

        if (str_starts_with(static::class, 'Tests\\Feature\\')) {
            // Existing DatabaseTransactions traits keep their rollback behavior.
            // Cover feature classes without that trait too (e.g. HTTP smoke tests).
            if (DB::transactionLevel() === 0) {
                DB::beginTransaction();
                $this->beforeApplicationDestroyed(fn () => DB::rollBack());
            }
            foreach (User::ADMIN_ROLES as $name) {
                Role::firstOrCreate(['name' => $name], ['description' => 'Synthetic test role']);
            }
        }
    }
}
