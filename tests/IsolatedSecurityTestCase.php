<?php

namespace Tests;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

abstract class IsolatedSecurityTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Check configuration before connecting, starting transactions, or creating fixtures.
        // No RefreshDatabase: the isolated schema must already be provisioned by its owner.
        $connection = config('database.default');
        $database = (string) config("database.connections.$connection.database");
        $dedicated = $database === ':memory:' || preg_match('/(?:_test|_testing)$/', $database);
        if (! app()->environment('testing') || ! $dedicated || getenv('WPITV_TEST_DATABASE_ISOLATED') !== 'true') {
            $this->markTestSkipped('FEATURE SECURITY TEST ENVIRONMENT NOT ISOLATED');
        }

        config(['session.driver' => 'array', 'cache.default' => 'array', 'mail.default' => 'array']);
        DB::beginTransaction();
        $this->beforeApplicationDestroyed(function () {
            DB::rollBack();
        });
        foreach (User::ADMIN_ROLES as $name) {
            Role::firstOrCreate(['name' => $name], ['description' => 'Isolated security test role']);
        }
    }

    protected function roleUser(string $role, bool $active = true): User
    {
        $record = Role::firstOrCreate(['name' => $role], ['description' => 'Isolated security test role']);

        return User::factory()->create(['role_id' => $record->id, 'status' => $active])->load('role');
    }
}
