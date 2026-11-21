<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\TestCase as LaravelTestCase;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;
use Tests\EquipmentConcurrencyTestCase;
use Tests\Support\EquipmentConcurrencyGuard as Guard;

/** No application bootstrap, environment files, PDO instances or database access. */
class EquipmentConcurrencyGuardTest extends TestCase
{
    private static function definitions(): array
    {
        return array_fill_keys(Guard::TABLES, 'CREATE TABLE `synthetic` (`id` bigint unsigned NOT NULL, CHECK (`id` > 0)) ENGINE=InnoDB');
    }

    private static function approval(): array
    {
        return ['source_database' => 'wpitv', 'database' => 'wpitv_testing', 'username' => 'wpitv_test_runner',
            'host' => '127.0.0.1', 'port' => '3306', 'tables' => Guard::TABLES,
            'concurrency_schema_format' => 'show-create-v1', 'concurrency_schema_hash' => Guard::schemaHash(self::definitions())];
    }

    private static function connection(): array
    {
        return ['driver' => 'mysql', 'database' => 'wpitv_testing', 'username' => 'wpitv_test_runner',
            'host' => '127.0.0.1', 'port' => 3306, 'timezone' => '+00:00', 'isolation_level' => 'REPEATABLE READ', 'strict' => true];
    }

    private static function identity(): array
    {
        return ['database_name' => 'wpitv_testing', 'account' => 'wpitv_test_runner@localhost', 'active_roles' => 'NONE',
            'autocommit' => '1', 'foreign_key_checks' => '1', 'unique_checks' => '1', 'time_zone' => '+00:00',
            'isolation_level' => 'REPEATABLE-READ', 'connection_id' => '101', 'server_uuid' => 'synthetic-server'];
    }

    private static function runtime(): array
    {
        $root = 'C:/project/storage/app/testing/runtime-123-aabbccddeeff';

        return ['environment' => 'testing', 'configuration_cached' => false, 'default_connection' => 'mysql',
            'connections' => ['mysql', 'equipment_peer'], 'isolated' => 'true', 'cache' => 'array', 'cache_driver' => 'array',
            'session' => 'array', 'mail' => 'array', 'mail_transport' => 'array', 'queue' => 'sync', 'queue_driver' => 'sync',
            'log' => 'null', 'filesystem' => 'local', 'storage' => $root, 'views' => $root.'/framework/views',
            'local' => $root.'/app/private', 'public' => $root.'/app/public', 'disk_names' => ['local', 'public'], 'links' => [],
            'config_cache' => $root.'/bootstrap/config.php', 'routes_cache' => $root.'/bootstrap/routes.php',
            'events_cache' => $root.'/bootstrap/events.php', 'services_cache' => $root.'/bootstrap/services.php',
            'packages_cache' => $root.'/bootstrap/packages.php'];
    }

    public function test_base_is_separate_from_existing_transaction_wrappers(): void
    {
        $class = new ReflectionClass(EquipmentConcurrencyTestCase::class);
        $this->assertTrue($class->isAbstract());
        $this->assertSame(LaravelTestCase::class, $class->getParentClass()->getName());
        $this->assertTrue($class->getMethod('setUp')->isFinal());
        $this->assertTrue($class->getMethod('tearDown')->isFinal());
        $this->assertTrue($class->getMethod('stopEquipmentWorkers')->isAbstract());
        $this->assertTrue($class->getMethod('cleanUpEquipmentFixtures')->isAbstract());
    }

    public function test_expected_readonly_preflight_inputs_are_accepted(): void
    {
        Guard::assertOptIn('true');
        Guard::assertProcessMode([]);
        Guard::assertTraits([]);
        Guard::assertApproval(self::approval());
        Guard::assertConnectionConfig(self::connection(), self::approval());
        Guard::assertRuntime(self::runtime(), 'C:/project', 'C:/project/storage/app/testing/runtime-123-aabbccddeeff');
        Guard::assertSession(self::identity(), 0, false);
        Guard::assertGrants(['GRANT USAGE ON *.* TO `wpitv_test_runner`@`localhost`',
            'GRANT SELECT, INSERT, UPDATE, DELETE ON `wpitv_testing`.* TO `wpitv_test_runner`@`localhost`']);
        Guard::assertPair(self::identity(), [...self::identity(), 'connection_id' => 102], false);
        Guard::assertSchema(array_fill_keys(Guard::TABLES, 'InnoDB'), self::definitions(), self::approval());
        $this->addToAssertionCount(10);
    }

    public static function dangerousInputs(): iterable
    {
        yield 'no explicit opt in' => ['optIn', false];
        yield 'nonliteral opt in' => ['optIn', '1'];
        yield 'Laravel parallel database provisioning' => ['process', ['parallel' => '1']];
        yield 'parallel testing database suffix token' => ['process', ['token' => '1']];
        foreach (['DatabaseTransactions', 'RefreshDatabase', 'DatabaseMigrations', 'DatabaseTruncation', 'WithCachedConfig', 'WithCachedRoutes'] as $trait) {
            yield $trait => ['traits', ['Illuminate\\Foundation\\Testing\\'.$trait]];
        }
        yield 'old seventeen table approval' => ['approval', [...self::approval(), 'tables' => array_values(array_filter(Guard::TABLES, fn ($table) => ! str_starts_with($table, 'inventory_') && ! str_starts_with($table, 'equipment_')))]];
        yield 'unreviewed schema approval' => ['approval', [...self::approval(), 'concurrency_schema_hash' => '']];
        yield 'duplicate approved table' => ['approval', [...self::approval(), 'tables' => [...Guard::TABLES, 'users']]];
        foreach (['database' => 'wpitv', 'username' => 'root', 'host' => ['127.0.0.1', 'remote'], 'port' => 3307,
            'driver' => 'sqlite', 'url' => 'mysql://SYNTHETIC_SECRET', 'read' => ['database' => 'wpitv'],
            'write' => ['database' => 'wpitv'], 'unix_socket' => '/socket', 'prefix' => 'wpitv.',
            'timezone' => '+07:00', 'isolation_level' => 'READ COMMITTED', 'strict' => false,
            'options' => [PDO::ATTR_PERSISTENT => true]] as $key => $value) {
            yield 'connection '.$key => ['connection', [...self::connection(), $key => $value]];
        }
        yield 'unreviewed PDO init SQL' => ['connection', [...self::connection(), 'options' => [PDO::MYSQL_ATTR_INIT_COMMAND => 'USE wpitv']]];
        foreach (['environment' => 'local', 'configuration_cached' => true, 'default_connection' => 'equipment_peer',
            'connections' => ['mysql', 'equipment_peer', 'sqlite'], 'isolated' => 'false',
            'cache' => 'database', 'cache_driver' => 'database', 'session' => 'database', 'log' => 'stack',
            'queue_driver' => 'database', 'mail_transport' => 'smtp', 'filesystem' => 'public',
            'storage' => 'C:/project/storage', 'views' => 'C:/project/storage/framework/views',
            'local' => 'C:/project/storage/app/testing/runtime-123-aabbccddeeff/app/../private',
            'public' => 'C:/project/public/storage', 'disk_names' => ['local', 'public', 's3'], 'links' => ['public/storage' => '/real'],
            'config_cache' => 'C:/project/bootstrap/cache/config.php', 'routes_cache' => 'C:/project/bootstrap/cache/routes.php'] as $key => $value) {
            yield 'runtime '.$key => ['runtime', [...self::runtime(), $key => $value]];
        }
        foreach (['database_name' => 'wpitv', 'account' => 'root@localhost', 'active_roles' => '`admin`@`%`',
            'autocommit' => '0', 'foreign_key_checks' => 0, 'unique_checks' => 0,
            'time_zone' => 'SYSTEM', 'isolation_level' => 'READ-COMMITTED', 'connection_id' => 0] as $key => $value) {
            yield 'server '.$key => ['session', [...self::identity(), $key => $value]];
        }
    }

    #[DataProvider('dangerousInputs')]
    public function test_guards_reject_unsafe_inputs_without_database_access(string $check, mixed $input): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('TEST SAFETY ABORT: EQCT_');
        match ($check) {
            'optIn' => Guard::assertOptIn($input),
            'process' => Guard::assertProcessMode($input),
            'traits' => Guard::assertTraits($input),
            'approval' => Guard::assertApproval($input),
            'connection' => Guard::assertConnectionConfig($input, self::approval()),
            'runtime' => Guard::assertRuntime($input, 'C:/project', 'C:/project/storage/app/testing/runtime-123-aabbccddeeff'),
            'session' => Guard::assertSession($input, 0, false),
        };
    }

    public static function unsafeGrants(): iterable
    {
        yield 'global SELECT' => ['GRANT SELECT ON *.* TO `wpitv_test_runner`@`localhost`'];
        yield 'source access' => ['GRANT SELECT ON `wpitv`.* TO `wpitv_test_runner`@`localhost`'];
        yield 'all privileges' => ['GRANT ALL PRIVILEGES ON `wpitv_testing`.* TO `wpitv_test_runner`@`localhost`'];
        yield 'DDL' => ['GRANT CREATE ON `wpitv_testing`.* TO `wpitv_test_runner`@`localhost`'];
        yield 'execute' => ['GRANT EXECUTE ON `wpitv_testing`.* TO `wpitv_test_runner`@`localhost`'];
        yield 'grant option' => ['GRANT SELECT ON `wpitv_testing`.* TO `wpitv_test_runner`@`localhost` WITH GRANT OPTION'];
        yield 'assigned role' => ['GRANT `admin`@`%` TO `wpitv_test_runner`@`localhost`'];
        yield 'table privilege' => ['GRANT SELECT ON `wpitv_testing`.`users` TO `wpitv_test_runner`@`localhost`'];
        yield 'wrong authenticated account' => ['GRANT SELECT ON `wpitv_testing`.* TO `wpitv_test_runner`@`%`'];
        yield 'missing DML privileges' => ['GRANT SELECT ON `wpitv_testing`.* TO `wpitv_test_runner`@`localhost`'];
    }

    #[DataProvider('unsafeGrants')]
    public function test_privilege_guard_fails_closed(string $grant): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('TEST SAFETY ABORT: EQCT_');
        Guard::assertGrants([$grant]);
    }

    public function test_no_outer_transaction_and_independent_connections_are_required(): void
    {
        $checks = [
            fn () => Guard::assertSession(self::identity(), 1, false),
            fn () => Guard::assertSession(self::identity(), 0, true),
            fn () => Guard::assertPair(self::identity(), [...self::identity(), 'connection_id' => 102], true),
            fn () => Guard::assertPair(self::identity(), [...self::identity(), 'connection_id' => 101], false),
            fn () => Guard::assertPair(self::identity(), [...self::identity(), 'connection_id' => 102, 'server_uuid' => 'other-server'], false),
        ];
        foreach ($checks as $check) {
            try {
                $check();
                $this->fail('Unsafe transaction/connection accepted.');
            } catch (RuntimeException $exception) {
                $this->assertStringStartsWith('TEST SAFETY ABORT: EQCT_', $exception->getMessage());
                $this->assertNull($exception->getPrevious());
            }
        }
    }

    public function test_schema_hash_ignores_counters_and_order_but_preserves_constraints(): void
    {
        $before = ['b' => "CREATE TABLE `b` (`id` int) ENGINE=InnoDB AUTO_INCREMENT=42\r\n", 'a' => 'CREATE TABLE `a` (`id` int, CHECK (`id` > 0))'];
        $after = ['a' => $before['a'], 'b' => "CREATE TABLE `b` (`id` int) ENGINE=InnoDB AUTO_INCREMENT=900\n"];
        $this->assertSame(Guard::schemaHash($before), Guard::schemaHash($after));
        $after['a'] = 'CREATE TABLE `a` (`id` int)';
        $this->assertNotSame(Guard::schemaHash($before), Guard::schemaHash($after));
        $changed = self::definitions();
        $changed['equipment_loan_items'] .= ' /* constraint differs */';
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('EQCT_SCHEMA_HASH');
        Guard::assertSchema(array_fill_keys(Guard::TABLES, 'InnoDB'), $changed, self::approval());
    }

    public function test_guard_diagnostics_do_not_echo_untrusted_connection_data(): void
    {
        try {
            Guard::assertConnectionConfig([...self::connection(), 'url' => 'mysql://SYNTHETIC_SECRET'], self::approval());
            $this->fail('URL override accepted.');
        } catch (RuntimeException $exception) {
            $this->assertStringNotContainsString('SYNTHETIC_SECRET', $exception->getMessage());
            $this->assertNull($exception->getPrevious());
        }
    }
}
