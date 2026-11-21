<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\TestCase as LaravelTestCase;
use Illuminate\Support\Facades\Http;
use PDO;
use RuntimeException;
use Tests\Support\ReleaseConcurrencyGuard as Guard;
use Tests\Support\TestEnvironment;
use Throwable;

/** Opt-in foundation only. No fixture writes or runnable database tests here. */
abstract class ReleaseConcurrencyTestCase extends LaravelTestCase
{
    protected ?Connection $primary = null;

    protected ?Connection $peer = null;

    private array $approval = [];

    private bool $preflightComplete = false;

    private bool $disposed = false;

    private static bool $quarantined = false;

    private $suiteLock = null;

    private string $setupStage = 'APPLICATION';

    final public function createApplication()
    {
        Guard::assertOptIn(getenv('WPITV_RELEASE_CONCURRENCY'));
        Guard::assertProcessMode([
            'parallel' => $_SERVER['LARAVEL_PARALLEL_TESTING'] ?? getenv('LARAVEL_PARALLEL_TESTING'),
            'token' => $_SERVER['TEST_TOKEN'] ?? getenv('TEST_TOKEN'),
        ]);
        if (self::$quarantined) {
            throw new RuntimeException('TEST SAFETY ABORT: RLCT_QUARANTINED');
        }
        $this->traitsUsedByTest = array_flip(class_uses_recursive(static::class));
        Guard::assertTraits(array_keys($this->traitsUsedByTest));
        $root = dirname(__DIR__);
        $this->approval = json_decode((string) file_get_contents($root.'/storage/app/testing/approved.json'), true, 512, JSON_THROW_ON_ERROR);
        Guard::assertApproval($this->approval); // Old 17-table approval fails before connecting.
        TestEnvironment::initialize();
        $this->acquireSuiteLock($root);
        $app = require $root.'/bootstrap/app.php';
        $app->useStoragePath(TestEnvironment::runtime());
        $app->make(Kernel::class)->bootstrap();
        TestEnvironment::configure($app);
        $connection = $app['config']->get('database.connections.mysql');
        // Process-local settings only; preserve credentials and the main .env.
        $connection['timezone'] = '+00:00';
        $connection['isolation_level'] = 'REPEATABLE READ';
        Guard::assertConnectionConfig($connection, $this->approval);
        $connection['options'][PDO::ATTR_PERSISTENT] = false;
        $app['config']->set('database.connections', ['mysql' => $connection, 'release_peer' => $connection]);
        $app['config']->set('logging.default', 'null');

        return $app;
    }

    final protected function setUp(): void
    {
        try {
            parent::setUp();
            $this->setupStage = 'RUNTIME';
            $this->assertRuntimeSafety();
            $this->withoutVite();
            Http::preventStrayRequests();
            // Distinct Laravel connection names create distinct nonpersistent PDOs.
            // Neither connection is wrapped in a test-level transaction.
            $this->primary = $this->app['db']->connection('mysql');
            $this->peer = $this->app['db']->connection('release_peer');
            $this->setupStage = 'CONNECTIONS';
            $this->verifyConnections();
            $this->setupStage = 'SCHEMA';
            $this->verifySchema();
            $this->preflightComplete = true;
            $this->setupStage = 'FIXTURES';
            $this->setUpReleaseFixtures();
        } catch (Throwable $exception) {
            $code = $exception instanceof RuntimeException && preg_match('/\ATEST SAFETY ABORT: (RLCT_[A-Z_]+)\z/', $exception->getMessage(), $match)
                ? $match[1] : 'RLCT_SETUP_'.$this->setupStage;
            $cleanupFailed = $this->disposeRelease();
            // Never expose authentication/SQL details or retain a previous error.
            throw new RuntimeException($cleanupFailed
                ? 'TEST SAFETY ABORT: RLCT_SETUP_CLEANUP; review the synthetic run journal.'
                : 'TEST SAFETY ABORT: '.$code);
        }
    }

    final protected function tearDown(): void
    {
        if ($this->disposeRelease()) {
            throw new RuntimeException('TEST SAFETY ABORT: RLCT_CLEANUP; review the synthetic run journal.');
        }
    }

    protected function setUpReleaseFixtures(): void {}

    /** Join/stop every child process before releasing locks or removing fixtures. */
    abstract protected function stopReleaseWorkers(): void;

    /** Exact owned IDs/tokens only, reverse FK order; never baseline rows or roles. */
    abstract protected function cleanUpReleaseFixtures(): void;

    private function finishReleaseScenario(): void
    {
        if (! $this->preflightComplete) {
            return;
        }
        $this->stopReleaseWorkers();
        foreach ([$this->primary, $this->peer] as $connection) {
            if ($connection->transactionLevel() > 0) {
                $connection->rollBack(0);
            }
            if ($connection->getPdo()->inTransaction()) {
                $connection->getPdo()->rollBack();
            }
        }
        // Check identities/privileges/runtime again before any derived cleanup DML.
        $this->assertRuntimeSafety();
        $this->verifyConnections();
        $this->cleanUpReleaseFixtures();
        $this->verifyConnections(); // Cleanup must commit, not leave an outer transaction.
        $this->preflightComplete = false;
    }

    private function assertRuntimeSafety(): void
    {
        $config = $this->app['config'];
        foreach ($config->get('database.connections') as $connection) {
            Guard::assertConnectionConfig($connection, $this->approval);
        }
        foreach ([$this->app->storagePath(), $config->get('view.compiled'), $config->get('filesystems.disks.local.root'), $config->get('filesystems.disks.public.root')] as $path) {
            if (realpath($path) === false || strcasecmp(str_replace('\\', '/', realpath($path)), str_replace('\\', '/', $path)) !== 0) {
                throw new RuntimeException('TEST SAFETY ABORT: RLCT_RUNTIME_REALPATH');
            }
        }
        Guard::assertRuntime([
            'environment' => $this->app->environment(), 'configuration_cached' => $this->app->configurationIsCached(),
            'default_connection' => $config->get('database.default'), 'connections' => array_keys($config->get('database.connections')),
            'isolated' => getenv('WPITV_TEST_DATABASE_ISOLATED'),
            'cache' => $config->get('cache.default'), 'cache_driver' => $config->get('cache.stores.array.driver'),
            'session' => $config->get('session.driver'), 'mail' => $config->get('mail.default'), 'mail_transport' => $config->get('mail.mailers.array.transport'),
            'queue' => $config->get('queue.default'), 'queue_driver' => $config->get('queue.connections.sync.driver'),
            'log' => $config->get('logging.default'), 'filesystem' => $config->get('filesystems.default'),
            'storage' => $this->app->storagePath(), 'views' => $config->get('view.compiled'),
            'local' => $config->get('filesystems.disks.local.root'), 'public' => $config->get('filesystems.disks.public.root'),
            'disk_names' => array_keys($config->get('filesystems.disks')), 'links' => $config->get('filesystems.links'),
            'config_cache' => $this->app->getCachedConfigPath(), 'routes_cache' => $this->app->getCachedRoutesPath(),
            'events_cache' => $this->app->getCachedEventsPath(), 'services_cache' => $this->app->getCachedServicesPath(),
            'packages_cache' => $this->app->getCachedPackagesPath(),
        ], dirname(__DIR__), TestEnvironment::runtime());
    }

    private function inspectConnection(Connection $connection): array
    {
        Guard::assertConnectionConfig($connection->getConfig(), $this->approval);
        $identity = (array) $connection->selectOne('SELECT DATABASE() AS database_name, CURRENT_USER() AS account, CURRENT_ROLE() AS active_roles,
            CONNECTION_ID() AS connection_id, @@server_uuid AS server_uuid, @@autocommit AS autocommit,
            @@foreign_key_checks AS foreign_key_checks, @@unique_checks AS unique_checks,
            @@session.time_zone AS time_zone, @@transaction_isolation AS isolation_level', [], false);
        Guard::assertSession($identity, $connection->transactionLevel(), $connection->getPdo()->inTransaction());
        Guard::assertGrants(array_map(fn ($row) => array_values((array) $row)[0], $connection->select('SHOW GRANTS', [], false)));
        // Read-only denial probe. Unexpected success or another error fails closed.
        $denied = false;
        try {
            $connection->select('SELECT 1 FROM `wpitv`.`users` LIMIT 0', [], false);
        } catch (QueryException $exception) {
            $denied = in_array((int) ($exception->errorInfo[1] ?? 0), [1044, 1142], true);
        }
        if (! $denied) {
            throw new RuntimeException('TEST SAFETY ABORT: RLCT_SOURCE_ACCESS');
        }

        return $identity;
    }

    private function verifyConnections(): void
    {
        Guard::assertPair($this->inspectConnection($this->primary), $this->inspectConnection($this->peer), $this->primary->getPdo() === $this->peer->getPdo());
    }

    private function verifySchema(): void
    {
        $engines = [];
        foreach ($this->primary->select("SELECT TABLE_NAME AS table_name, ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME", [], false) as $table) {
            $engines[$table->table_name] = $table->engine;
        }
        $definitions = [];
        foreach (Guard::approvedTables($this->approval) as $table) {
            // Table identifiers come exclusively from the fixed guard allowlist.
            $definitions[$table] = array_values((array) $this->primary->selectOne('SHOW CREATE TABLE `'.$table.'`', [], false))[1];
        }
        Guard::assertSchema($engines, $definitions, $this->approval);
    }

    private function disconnectRelease(): void
    {
        $this->primary?->disconnect();
        $this->peer?->disconnect();
        $this->primary = $this->peer = null;
    }

    private function disposeRelease(): bool
    {
        if ($this->disposed) {
            return false;
        }
        $this->disposed = true;
        $failed = false;
        try {
            $this->finishReleaseScenario();
        } catch (Throwable) {
            $failed = true;
        }
        try {
            $this->disconnectRelease();
        } catch (Throwable) {
            $failed = true;
        }
        try {
            if ($this->app !== null) {
                parent::tearDown();
            }
        } catch (Throwable) {
            $failed = true;
        }

        if (is_resource($this->suiteLock)) {
            flock($this->suiteLock, LOCK_UN);
            fclose($this->suiteLock);
            $this->suiteLock = null;
        }
        if ($failed) {
            self::$quarantined = true;
        }

        return $failed;
    }

    private function acquireSuiteLock(string $root): void
    {
        $this->suiteLock = fopen($root.'/storage/app/testing/release-concurrency.lock', 'c');
        if ($this->suiteLock === false || ! flock($this->suiteLock, LOCK_EX | LOCK_NB)) {
            throw new RuntimeException('TEST SAFETY ABORT: RLCT_SUITE_BUSY');
        }
        $journals = glob($root.'/storage/app/testing/release-runs/*.json');
        if ($journals === false || $journals !== []) {
            throw new RuntimeException('TEST SAFETY ABORT: RLCT_PENDING_JOURNAL');
        }
    }
}
