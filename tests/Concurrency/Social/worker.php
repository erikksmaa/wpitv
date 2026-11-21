<?php

ini_set('display_errors', '0');
ini_set('log_errors', '0');
require dirname(__DIR__, 3).'/vendor/autoload.php';

use App\Exceptions\TVClubSocialException;
use App\Models\TVClubSocialAccount;
use App\Models\User;
use App\Services\TVClubSocialService;
use Illuminate\Contracts\Console\Kernel;
use Tests\Support\EquipmentConcurrencyGuard as Guard;
use Tests\Support\TestEnvironment;

function emit(array $value): void
{
    fwrite(STDOUT, json_encode($value, JSON_THROW_ON_ERROR).PHP_EOL);
    fflush(STDOUT);
}
try {
    $root = dirname(__DIR__, 3);
    $jobPath = realpath($argv[1] ?? '');
    if (! $jobPath || ! str_starts_with(str_replace('\\', '/', $jobPath), str_replace('\\', '/', $root).'/storage/app/testing/runtime-')) {
        throw new RuntimeException('JOB_PATH');
    }
    $job = json_decode(file_get_contents($jobPath), true, 512, JSON_THROW_ON_ERROR);
    if (str_replace('\\', '/', $job['go_file']) !== str_replace('\\', '/', substr($jobPath, 0, -5).'.go')) {
        throw new RuntimeException('GO_PATH');
    }
    TestEnvironment::initialize();
    $app = require $root.'/bootstrap/app.php';
    $app->useStoragePath(TestEnvironment::runtime());
    $app->make(Kernel::class)->bootstrap();
    TestEnvironment::configure($app);
    $config = $app['config']->get('database.connections.mysql');
    $config['timezone'] = '+00:00';
    $config['isolation_level'] = 'REPEATABLE READ';
    $config['options'][PDO::ATTR_PERSISTENT] = false;
    $approval = json_decode(file_get_contents($root.'/storage/app/testing/approved.json'), true, 512, JSON_THROW_ON_ERROR);
    Guard::assertConnectionConfig($config, $approval);
    $app['config']->set('database.connections', ['mysql' => $config]);
    $app['config']->set('logging.default', 'null');
    if (! $app->environment('testing') || $app->configurationIsCached() || $app['config']->get('cache.default') !== 'array' || $app['config']->get('session.driver') !== 'array' || $app->storagePath() !== TestEnvironment::runtime()) {
        throw new RuntimeException('RUNTIME');
    }
    $db = $app['db']->connection();
    $i = (array) $db->selectOne('SELECT DATABASE() AS database_name,CURRENT_USER() AS account,CURRENT_ROLE() AS active_roles,CONNECTION_ID() AS connection_id,@@server_uuid AS server_uuid,@@autocommit AS autocommit,@@foreign_key_checks AS foreign_key_checks,@@unique_checks AS unique_checks,@@session.time_zone AS time_zone,@@transaction_isolation AS isolation_level');
    Guard::assertSession($i, $db->transactionLevel(), $db->getPdo()->inTransaction());
    Guard::assertGrants(array_map(fn ($r) => array_values((array) $r)[0], $db->select('SHOW GRANTS')));
    $engines = [];
    foreach ($db->select("SELECT TABLE_NAME AS name,ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME") as $row) {
        $engines[$row->name] = $row->engine;
    }
    $ddl = [];
    foreach (Guard::approvedTables($approval) as $table) {
        $ddl[$table] = array_values((array) $db->selectOne('SHOW CREATE TABLE `'.$table.'`'))[1];
    }Guard::assertSchema($engines, $ddl, $approval);
    $db->statement('SET SESSION innodb_lock_wait_timeout=10');
    $actor = User::with('role')->findOrFail($job['actor_id']);
    if (! str_starts_with($actor->username, 'EQCT_')) {
        throw new RuntimeException('ACTOR');
    }
    if (! $actor->isTVClub() || ! preg_match('/\AEQCT_[A-F0-9]+_\d+\z/', $actor->username)) {
        throw new RuntimeException('ACTOR_SCOPE');
    }
    $correction = isset($job['account_id']);
    if ($correction) {
        $account = TVClubSocialAccount::findOrFail($job['account_id']);
        if ($account->account_key !== preg_replace('/_\d+$/', '', $actor->username)) {
            throw new RuntimeException('ACCOUNT_SCOPE');
        }
        $observation = $account->observations()->findOrFail($job['observation_id']);
    }
    $db->beforeExecuting(function ($query) use ($job, $i, $correction): void {
        $sql = strtolower($query);
        $atBarrier = $correction
            ? str_contains($sql, 'from `tvclub_social_accounts`') && str_ends_with($sql, 'for update')
            : str_starts_with($sql, 'insert into `tvclub_social_report_versions`');
        if (! $atBarrier) {
            return;
        }
        emit(['phase' => 'ready', 'connection_id' => $i['connection_id'], 'uuid' => $i['server_uuid']]);
        $deadline = microtime(true) + 20;
        while (! is_file($job['go_file'])) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException('GO_TIMEOUT');
            }
            clearstatcache();
            usleep(10000);
        }
    });
    try {
        $service = app(TVClubSocialService::class);
        if ($correction) {
            $service->observeAccount($actor, $account->id, $observation->observed_at, ['followers' => 42],
                correctsId: $observation->id, reason: 'Concurrent synthetic correction');
        } else {
            $service->publish($actor, '2026-10', 0);
        }
        emit(['phase' => 'result', 'code' => 'OK']);
    } catch (TVClubSocialException $e) {
        emit(['phase' => 'result', 'code' => $e->errorCode]);
    }
} catch (Throwable) {
    emit(['phase' => 'fatal', 'code' => 'SAFE_FAILURE']);
    exit(1);
}
