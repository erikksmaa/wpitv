<?php

namespace Tests\Support;

use PDO;
use RuntimeException;

/** Pure checks only: no environment loading, filesystem changes or connections. */
final class ReleaseConcurrencyGuard
{
    public const TABLES = ['live_stream_events', 'live_streams', 'migrations', 'monthly_activity_attachments', 'monthly_activity_events', 'monthly_activity_items', 'monthly_activity_reports', 'monthly_activity_versions', 'ms_banner', 'ms_news', 'ms_program', 'ms_video', 'roles', 'tr_perliputan', 'tr_usercounter', 'tvclub_generations', 'tvclub_group_members', 'tvclub_groups', 'tvclub_members', 'tvclub_organization_members', 'tvclub_organization_positions', 'users', 'wpitv_agendas', 'wpitv_organization_positions'];

    public static function approvedTables(array $approved): array
    {
        $tables = $approved['tables'] ?? [];
        sort($tables);
        self::require($tables === self::TABLES, 'APPROVAL_TABLES');

        return $tables;
    }

    public static function assertOptIn(string|false $value): void
    {
        self::require($value === 'true', 'OPT_IN');
    }

    public static function assertProcessMode(array $process): void
    {
        foreach (['parallel', 'token'] as $key) {
            self::require(in_array($process[$key] ?? false, [false, null, ''], true), 'PARALLEL_RUNNER');
        }
    }

    public static function assertTraits(array $traits): void
    {
        foreach (['DatabaseTransactions', 'RefreshDatabase', 'DatabaseMigrations', 'DatabaseTruncation', 'WithCachedConfig', 'WithCachedRoutes'] as $trait) {
            self::require(! in_array('Illuminate\\Foundation\\Testing\\'.$trait, $traits, true), 'TEST_TRAIT');
        }
    }

    public static function assertApproval(array $approved): void
    {
        self::require(($approved['source_database'] ?? null) === 'wpitv'
            && ($approved['database'] ?? null) === 'wpitv_rebuild_testing'
            && ($approved['username'] ?? null) === 'wpitv_rebuild_runner'
            && ($approved['host'] ?? null) === '127.0.0.1'
            && (string) ($approved['port'] ?? '') === '3306', 'APPROVAL_IDENTITY');
        self::approvedTables($approved);
        self::require(($approved['concurrency_schema_format'] ?? null) === 'show-create-v1'
            && is_string($approved['concurrency_schema_hash'] ?? null)
            && preg_match('/\A[a-f0-9]{64}\z/', $approved['concurrency_schema_hash']) === 1, 'APPROVAL_HASH');
    }

    public static function assertConnectionConfig(array $connection, array $approved): void
    {
        self::assertApproval($approved);
        self::require(($connection['driver'] ?? null) === 'mysql'
            && ($connection['database'] ?? null) === $approved['database']
            && ($connection['username'] ?? null) === $approved['username']
            && ($connection['host'] ?? null) === $approved['host']
            && (string) ($connection['port'] ?? '') === (string) $approved['port']
            && empty($connection['url']) && empty($connection['unix_socket'])
            && empty($connection['read']) && empty($connection['write'])
            && ($connection['prefix'] ?? '') === '', 'CONNECTION_CONFIG');
        self::require(empty($connection['options'][PDO::ATTR_PERSISTENT])
            && empty($connection['options'][PDO::MYSQL_ATTR_INIT_COMMAND]), 'PDO_OPTIONS');
        self::require(($connection['timezone'] ?? null) === '+00:00'
            && ($connection['isolation_level'] ?? null) === 'REPEATABLE READ'
            && ($connection['strict'] ?? null) === true, 'SESSION_CONFIG');
    }

    public static function assertRuntime(array $state, string $project, string $runtime): void
    {
        $project = self::path($project);
        $runtime = self::path($runtime);
        self::require(preg_match('~\A'.preg_quote($project, '~').'/storage/app/testing/runtime-[0-9]+-[a-f0-9]{12}\z~', $runtime) === 1, 'RUNTIME_ROOT');
        self::require(($state['environment'] ?? null) === 'testing'
            && ($state['configuration_cached'] ?? true) === false
            && ($state['default_connection'] ?? null) === 'mysql'
            && ($state['connections'] ?? null) === ['mysql', 'release_peer']
            && ($state['isolated'] ?? null) === 'true', 'RUNTIME_IDENTITY');
        foreach (['cache' => 'array', 'cache_driver' => 'array', 'session' => 'array', 'mail' => 'array',
            'mail_transport' => 'array', 'queue' => 'sync', 'queue_driver' => 'sync', 'log' => 'null', 'filesystem' => 'local'] as $key => $value) {
            self::require(($state[$key] ?? null) === $value, 'RUNTIME_SERVICES');
        }
        foreach (['storage' => '', 'views' => '/framework/views', 'local' => '/app/private', 'public' => '/app/public'] as $key => $suffix) {
            self::require(self::path($state[$key] ?? '') === $runtime.$suffix, 'RUNTIME_PATH');
        }
        self::require(($state['disk_names'] ?? null) === ['local', 'public'] && ($state['links'] ?? null) === [], 'RUNTIME_DISKS');
        foreach (['config', 'routes', 'events', 'services', 'packages'] as $key) {
            self::require(str_starts_with(self::path($state[$key.'_cache'] ?? ''), $runtime.'/bootstrap/'), 'RUNTIME_CACHE');
        }
    }

    private static function path(string $path): string
    {
        $path = rtrim(str_replace('\\', '/', $path), '/');
        self::require($path !== '' && ! str_contains($path, "\0") && ! preg_match('~(?:\A|/)\.{1,2}(?:/|\z)~', $path), 'PATH');

        return $path;
    }

    public static function assertSession(array $identity, int $level, bool $pdoTransaction): void
    {
        self::require(($identity['database_name'] ?? null) === 'wpitv_rebuild_testing'
            && ($identity['account'] ?? null) === 'wpitv_rebuild_runner@localhost'
            && ($identity['active_roles'] ?? null) === 'NONE', 'SERVER_IDENTITY');
        self::require($level === 0 && ! $pdoTransaction
            && (int) ($identity['autocommit'] ?? 0) === 1, 'OUTER_TRANSACTION');
        self::require((int) ($identity['foreign_key_checks'] ?? 0) === 1
            && (int) ($identity['unique_checks'] ?? 0) === 1
            && ($identity['time_zone'] ?? null) === '+00:00'
            && ($identity['isolation_level'] ?? null) === 'REPEATABLE-READ', 'SERVER_SESSION');
        self::require(ctype_digit((string) ($identity['connection_id'] ?? ''))
            && (int) $identity['connection_id'] > 0
            && is_string($identity['server_uuid'] ?? null) && $identity['server_uuid'] !== '', 'SERVER_CONNECTION');
    }

    /** Fail closed on roles, table grants, DDL, global privileges and GRANT OPTION. */
    public static function assertGrants(array $grants): void
    {
        $effective = [];
        foreach ($grants as $grant) {
            self::require(is_string($grant)
                && preg_match("~\\AGRANT ([A-Z, ]+) ON (\\*\\.\\*|`?wpitv_rebuild_testing`?\\.\\*) TO [`']wpitv_rebuild_runner[`']@[`']localhost[`']\\z~", $grant, $match) === 1, 'GRANTS');
            $privileges = array_map('trim', explode(',', $match[1]));
            if ($match[2] === '*.*') {
                self::require($privileges === ['USAGE'], 'GLOBAL_GRANTS');

                continue;
            }
            self::require(array_diff($privileges, ['SELECT', 'INSERT', 'UPDATE', 'DELETE']) === [], 'DATABASE_GRANTS');
            $effective = [...$effective, ...$privileges];
        }
        $effective = array_values(array_unique($effective));
        sort($effective);
        self::require($effective === ['DELETE', 'INSERT', 'SELECT', 'UPDATE'], 'DML_GRANTS');
    }

    public static function assertPair(array $first, array $second, bool $samePdo): void
    {
        self::require(! $samePdo && (string) $first['connection_id'] !== (string) $second['connection_id']
            && $first['server_uuid'] === $second['server_uuid']
            && $first['database_name'] === $second['database_name']
            && $first['account'] === $second['account'], 'INDEPENDENT_CONNECTIONS');
    }

    public static function schemaHash(array $definitions): string
    {
        ksort($definitions);
        foreach ($definitions as &$definition) {
            $definition = trim(str_replace(["\r\n", "\r"], "\n", $definition));
            $definition = preg_replace('/ AUTO_INCREMENT=[0-9]+\b/', '', $definition);
        }
        unset($definition);

        return hash('sha256', json_encode($definitions, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    public static function assertSchema(array $engines, array $definitions, array $approved): void
    {
        self::assertApproval($approved);
        $tables = array_keys($engines);
        sort($tables);
        $defined = array_keys($definitions);
        sort($defined);
        self::require($tables === self::approvedTables($approved) && $defined === $tables
            && array_unique(array_values($engines)) === ['InnoDB'], 'SCHEMA_TABLES');
        self::require(hash_equals($approved['concurrency_schema_hash'], self::schemaHash($definitions)), 'SCHEMA_HASH');
    }

    private static function require(bool $safe, string $code): void
    {
        if (! $safe) {
            throw new RuntimeException('TEST SAFETY ABORT: RLCT_'.$code);
        }
    }
}
