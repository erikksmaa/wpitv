<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestEnvironment;

require dirname(__DIR__).'/tests/bootstrap.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->useStoragePath(TestEnvironment::runtime());
$app->make(Kernel::class)->bootstrap();
TestEnvironment::configure($app);
echo json_encode([
    'APP_ENV' => app()->environment(),
    'DB_DATABASE' => DB::selectOne('SELECT DATABASE() AS name')->name,
    'CACHE_STORE' => config('cache.default'),
    'SESSION_DRIVER' => config('session.driver'),
    'local_config_cache_ignored' => ! app()->configurationIsCached(),
    'storage_isolated' => str_contains(storage_path(), 'storage/app/testing/runtime-'),
], JSON_PRETTY_PRINT).PHP_EOL;
