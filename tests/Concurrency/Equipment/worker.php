<?php

ini_set('display_errors', '0');
ini_set('log_errors', '0');
require dirname(__DIR__, 3).'/vendor/autoload.php';

use App\Exceptions\EquipmentOperationException;
use App\Livewire\Admin\Inventory\Units\Form;
use App\Models\EquipmentAuditEvent;
use App\Models\InventoryUnit;
use App\Models\User;
use App\Services\EquipmentLoanCheckout;
use App\Services\EquipmentLoanExtension;
use App\Services\EquipmentLoanLoss;
use App\Services\EquipmentLoanReturn;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\EquipmentConcurrencyGuard as Guard;
use Tests\Support\EquipmentPhotoFixtures;
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
    $db->beforeExecuting(function ($query) use (&$armed): void {
        if ($armed && str_contains(strtolower($query), 'for update')) {
            $armed = false;
            emit(['phase' => 'lock_attempt']);
        }
    });
    if (! empty($job['fail_audit'])) {
        EquipmentAuditEvent::creating(function (): void {
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
    if ($job['operation'] === 'create_product') {
        $input = $job['input'];
        Livewire::actingAs($actor)->test(Form::class)
            ->set('name', $input['name'])->set('category_id', $input['category_id'])
            ->set('brand', 'Sony')->set('model', 'A6400')
            ->set('photo', UploadedFile::fake()->image('kit.png'))->call('save')->assertHasNoErrors();
        $unit = InventoryUnit::where('category_id', $input['category_id'])->where('name', $input['name'])->sole();
        emit(['phase' => 'result', 'ok' => true, 'id' => $unit->id]);

        return;
    }
    $class = match ($job['operation']) {
        'checkout' => EquipmentLoanCheckout::class,'return' => EquipmentLoanReturn::class,'loss' => EquipmentLoanLoss::class,'extension' => EquipmentLoanExtension::class,default => throw new RuntimeException('OPERATION')
    };
    try {
        $service = $app->make($class);
        $result = $job['operation'] === 'checkout' ? $service->checkout($actor, $job['token'], $job['input']) : ($job['operation'] === 'return' ? $service->record($actor, $job['token'], $job['input'], EquipmentPhotoFixtures::evidence($job['input'])) : $service->record($actor, $job['token'], $job['input']));
        emit(['phase' => 'result', 'ok' => true, 'id' => $result->id]);
    } catch (EquipmentOperationException $e) {
        emit(['phase' => 'result', 'ok' => false, 'code' => $e->errorCode]);
    } catch (ValidationException) {
        emit(['phase' => 'result', 'ok' => false, 'code' => 'VALIDATION']);
    } catch (AuthorizationException) {
        emit(['phase' => 'result', 'ok' => false, 'code' => 'FORBIDDEN']);
    }
} catch (Throwable) {
    emit(['phase' => 'fatal', 'code' => 'SAFE_FAILURE']);
    exit(1);
}
