<?php

namespace Tests\Unit;

use App\Support\TestingDatabaseGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TestEnvironment;

class TestEnvironmentGuardTest extends TestCase
{
    public function test_resolved_guard_accepts_only_uncached_testing_database(): void
    {
        TestingDatabaseGuard::validateResolved('testing', 'wpitv_rebuild_testing');
        $this->addToAssertionCount(1);
    }

    public static function unsafeResolvedConfiguration(): iterable
    {
        yield 'wrong environment' => ['local', 'wpitv_rebuild_testing', false];
        yield 'application database' => ['testing', 'wpitv', false];
        yield 'stale cached configuration' => ['testing', 'wpitv_rebuild_testing', true];
    }

    #[DataProvider('unsafeResolvedConfiguration')]
    public function test_resolved_guard_rejects_wrong_or_cached_configuration(string $environment, string $database, bool $cached): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('TEST SAFETY ABORT');

        TestingDatabaseGuard::validateResolved($environment, $database, configurationCached: $cached);
    }

    public static function unsafeSettings(): iterable
    {
        yield 'local environment' => ['APP_ENV', 'local'];
        yield 'application database' => ['DB_DATABASE', 'wpitv'];
        yield 'unapproved test database' => ['DB_DATABASE', 'another_testing'];
        yield 'root identity' => ['DB_USERNAME', 'root'];
        yield 'remote host' => ['DB_HOST', 'example.com'];
        yield 'wrong port' => ['DB_PORT', '3307'];
        yield 'missing opt in' => ['WPITV_TEST_DATABASE_ISOLATED', 'false'];
        yield 'connection URL override' => ['DB_URL', 'mysql://unsafe'];
        yield 'alternative driver' => ['DB_CONNECTION', 'sqlite'];
    }

    #[DataProvider('unsafeSettings')]
    public function test_guard_rejects_unsafe_configuration_before_connection(string $key, string $value): void
    {
        $settings = [
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_HOST' => '127.0.0.1',
            'DB_PORT' => '3306', 'DB_DATABASE' => 'wpitv_rebuild_testing', 'DB_USERNAME' => 'wpitv_rebuild_runner',
            'WPITV_TEST_DATABASE_ISOLATED' => 'true', 'APP_KEY' => 'base64:'.base64_encode(str_repeat('t', 32)),
        ];
        $approved = ['source_database' => 'wpitv', 'database' => 'wpitv_rebuild_testing', 'host' => '127.0.0.1', 'port' => '3306', 'username' => 'wpitv_rebuild_runner'];
        $settings[$key] = $value;
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('TEST SAFETY ABORT');
        TestEnvironment::validate($settings, $approved);
    }
}
