<?php

ini_set('display_errors', '0');
ini_set('log_errors', '0');
require dirname(__DIR__, 3).'/vendor/autoload.php';

use App\Exceptions\LiveStreamOperationException;
use App\Models\LiveStream;
use App\Models\LiveStreamEvent;
use App\Models\User;
use App\Models\WpitvAgenda;
use App\Services\LiveStreamLifecycle;
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
    $armed = true;
    $locks = [];
    $db->beforeExecuting(function ($query) use (&$armed, &$locks): void {
        if (str_contains(strtolower($query), 'for update') && preg_match('/from `([^`]+)`/i', $query, $match)) {
            $locks[] = $match[1];
        }
        if ($armed && str_contains(strtolower($query), 'for update')) {
            $armed = false;
            emit(['phase' => 'lock_attempt']);
        }
    });
    if (! empty($job['fail_audit'])) {
        LiveStreamEvent::creating(function (): void {
            throw new RuntimeException('Synthetic audit failure');
        });
    }
    emit(['phase' => 'ready', 'connection_id' => $i['connection_id']]);
    $deadline = microtime(true) + 15;
    while (! is_file($job['go_file'])) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('GO_TIMEOUT');
        }clearstatcache();
        usleep(10000);
    }
    try {
        $service = $app->make(LiveStreamLifecycle::class);
        if ($job['operation'] === 'create') {
            $agenda = WpitvAgenda::findOrFail($job['agenda_id']);
            if ((int) $agenda->created_by !== (int) $job['owner_id'] || ! str_starts_with($agenda->title, 'EQCT_')) {
                throw new RuntimeException('SCOPE');
            }
            $service->create($actor, $agenda->id);
            emit(['phase' => 'result', 'ok' => true, 'locks' => $locks]);
            exit;
        }
        $s = LiveStream::findOrFail($job['stream_id']);
        if ((int) $s->created_by !== (int) $job['owner_id']) {
            throw new RuntimeException('SCOPE');
        }
        $result = match ($job['operation']) {
            'status' => $service->transition($actor, $s->id, $job['version'], $job['target'], 'Synthetic concurrent operation'),
            'agenda' => $service->saveAgenda($actor, $s->agenda_id, $job['version'], ['title' => $job['title']]),
            'config' => $service->updateConfiguration($actor, $s->id, $job['version'], 'Zyx_1234567'),
            'archive' => $service->linkArchive($actor, $s->id, $job['version'], $job['video_id']),
            default => throw new RuntimeException('OPERATION'),
        };
        emit(['phase' => 'result', 'ok' => true, 'locks' => $locks]);
    } catch (LiveStreamOperationException $e) {
        emit(['phase' => 'result', 'ok' => false, 'code' => $e->errorCode, 'locks' => $locks]);
    }
} catch (Throwable) {
    emit(['phase' => 'fatal', 'code' => 'SAFE_FAILURE']);
    exit(1);
}
