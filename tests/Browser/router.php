<?php

use App\Models\EquipmentLoan;
use App\Models\InventoryCategory;
use App\Models\InventoryUnit;
use App\Models\User;
use App\Support\EquipmentErrors;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Tests\Support\TestEnvironment;

// Loopback-only, explicit testing process. Authentication is a synthetic actingAs
// fixture, equivalent to the HTTP integration harness; no production server uses this file.
ini_set('display_errors', '0');
ini_set('log_errors', '0');
$root = dirname(__DIR__, 2);
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$asset = realpath($root.'/public'.$path);
if ($asset && str_starts_with(str_replace('\\', '/', $asset), str_replace('\\', '/', $root).'/public/') && is_file($asset)) {
    return false;
}
require $root.'/vendor/autoload.php';

final class EquipmentQaSession
{
    public function handle($request, $next)
    {
        $request->session()->put(['auth_started_at' => time(), 'auth_last_activity_at' => time(), 'auth.password_confirmed_at' => time(), 'password_hash_web' => auth()->user()->getAuthPassword()]);

        return $next($request);
    }
}
try {
    if (! in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
        http_response_code(403);
        exit;
    }
    $configPath = realpath(getenv('WPITV_QA_CONFIG'));
    if (! $configPath || ! str_starts_with(str_replace('\\', '/', $configPath), str_replace('\\', '/', $root).'/storage/app/testing/runtime-')) {
        throw new RuntimeException('QA_CONFIG');
    }
    $qa = json_decode(file_get_contents($configPath), true, 512, JSON_THROW_ON_ERROR);
    TestEnvironment::initialize();
    $app = require $root.'/bootstrap/app.php';
    $app->useStoragePath(TestEnvironment::runtime());
    $app->make(Kernel::class)->bootstrap();
    TestEnvironment::configure($app);
    $app['config']->set('logging.default', 'null');
    $app->make(ExceptionHandler::class)->map(function (Throwable $error) use ($configPath) {
        file_put_contents(dirname($configPath).'/diagnostics.jsonl', json_encode(['class' => get_class($error), 'file' => basename($error->getFile()), 'line' => $error->getLine()]).PHP_EOL, FILE_APPEND | LOCK_EX);

        return EquipmentErrors::forHandler($error, request());
    });
    $sessionDirectory = dirname($configPath).'/sessions';
    if (! is_dir($sessionDirectory)) {
        mkdir($sessionDirectory, 0700, true);
    }
    $app['config']->set(['session.driver' => 'file', 'session.files' => $sessionDirectory, 'session.cookie' => 'wpitv_isolated_qa_session']);
    if (getenv('WPITV_MONTHLY_ATTACHMENTS_QA') === 'true') {
        $app['config']->set('filesystems.disks.local.root', dirname($configPath).'/private');
    }
    if (getenv('WPITV_EQUIPMENT_PHOTO_QA') === 'true') {
        $app['config']->set('filesystems.disks.local.root', dirname($configPath).'/private');
        $app['config']->set('filesystems.disks.tmp-for-tests', ['driver' => 'local', 'root' => dirname($configPath).'/uploads', 'throw' => true]);
    }
    $identity = $app['db']->selectOne('SELECT DATABASE() AS db,CURRENT_USER() AS account');
    if ($identity->db !== 'wpitv_testing' || $identity->account !== 'wpitv_test_runner@localhost') {
        throw new RuntimeException('QA_IDENTITY');
    }
    $actor = User::with('role')->findOrFail($qa['actors'][$qa['role']]);
    if (! str_starts_with($actor->username, $qa['prefix'].'_')) {
        throw new RuntimeException('QA_ACTOR');
    }
    $app['auth']->guard()->setUser($actor);
    $middleware = $app['router']->getMiddlewareGroups()['web'];
    $position = array_search(StartSession::class, $middleware, true);
    if ($position === false) {
        throw new RuntimeException('QA_SESSION_ORDER');
    }
    array_splice($middleware, $position + 1, 0, [EquipmentQaSession::class]);
    $app['router']->middlewareGroup('web', $middleware);
    $app->make(Vite::class)->useHotFile(storage_path('qa-no-hot-file'));
    $write = fn ($row) => file_put_contents($qa['events'], json_encode($row, JSON_THROW_ON_ERROR).PHP_EOL, FILE_APPEND | LOCK_EX);
    EquipmentLoan::created(function ($loan) use ($qa, $write) {
        if (in_array($loan->recorded_by, $qa['actors'], true)) {
            $write(['table' => 'tokens', 'token' => $loan->submission_token]);
        }
    });
    InventoryCategory::created(function ($category) use ($qa, $write) {
        if (str_starts_with($category->name, $qa['prefix'])) {
            $write(['table' => 'categories', 'id' => $category->id]);
        }
    });
    InventoryUnit::created(function ($unit) use ($qa, $write) {
        if (str_starts_with($unit->inventory_code, $qa['prefix'])) {
            $write(['table' => 'units', 'id' => $unit->id]);
        }
    });
    $request = Request::capture();
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    $response = $kernel->handle($request);
    $response->send();
    $kernel->terminate($request, $response);
} catch (Throwable) {
    http_response_code(503);
    echo 'Isolated QA request failed [EQ_QA].';
}
