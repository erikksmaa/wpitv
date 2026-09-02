<?php

namespace App\Support;

use Illuminate\Foundation\Application;
use RuntimeException;

final class TestingDatabaseGuard
{
    public static function testingWasRequested(?array $arguments = null): bool
    {
        $arguments ??= $_SERVER['argv'] ?? [];

        if (getenv('APP_ENV') === 'testing' || ($_ENV['APP_ENV'] ?? null) === 'testing') {
            return true;
        }

        foreach ($arguments as $index => $argument) {
            if ($argument === '--env=testing' || ($argument === '--env' && ($arguments[$index + 1] ?? null) === 'testing')) {
                return true;
            }
        }

        return false;
    }

    public static function assertSafe(Application $app): void
    {
        if (! self::testingWasRequested()) {
            return;
        }

        self::validateResolved(
            environment: (string) $app->environment(),
            database: (string) $app['config']->get('database.connections.mysql.database'),
            sourceDatabase: (string) env('WPITV_APPLICATION_DATABASE', 'wpitv'),
            configurationCached: $app->configurationIsCached(),
        );
    }

    public static function validateResolved(
        string $environment,
        string $database,
        string $sourceDatabase = 'wpitv',
        bool $configurationCached = false,
    ): void {
        if (
            $environment !== 'testing'
            || $database !== 'wpitv_testing'
            || $database === $sourceDatabase
            || $configurationCached
        ) {
            throw new RuntimeException('TEST SAFETY ABORT: testing requires uncached configuration and the approved wpitv_testing database.');
        }
    }
}
