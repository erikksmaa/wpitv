<?php

namespace Tests\Support;

use Dotenv\Dotenv;
use Illuminate\Foundation\Application;
use RuntimeException;

final class TestEnvironment
{
    private static ?array $settings = null;

    private static string $runtime;

    public static function initialize(): void
    {
        if (self::$settings !== null) {
            return;
        }
        $root = dirname(__DIR__, 2);
        $envPath = $root.'/.env.testing';
        $manifestPath = $root.'/storage/app/testing/approved.json';
        if (! is_file($envPath) || ! is_file($manifestPath)) {
            throw new RuntimeException('TEST SAFETY ABORT: provision .env.testing and the approved isolated database first.');
        }
        $settings = Dotenv::parse(file_get_contents($envPath));
        $approved = json_decode(file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        self::validate($settings, $approved);
        $applicationEnv = is_file($root.'/.env') ? Dotenv::parse(file_get_contents($root.'/.env')) : [];
        $applicationCache = is_file($root.'/bootstrap/cache/config.php') ? require $root.'/bootstrap/cache/config.php' : [];
        $cachedConnection = $applicationCache['database']['default'] ?? 'mysql';
        $cachedDatabase = $applicationCache['database']['connections'][$cachedConnection]['database'] ?? null;
        if (in_array($settings['DB_DATABASE'], [$applicationEnv['DB_DATABASE'] ?? null, $cachedDatabase], true)) {
            throw new RuntimeException('TEST SAFETY ABORT: test database is also configured as the application database.');
        }
        self::$runtime = $root.'/storage/app/testing/runtime-'.getmypid().'-'.bin2hex(random_bytes(6));
        $cacheRoot = 'storage/app/testing/'.basename(self::$runtime).'/bootstrap';
        foreach (['app/private', 'app/public', 'framework/views', 'framework/cache', 'framework/sessions', 'framework/testing', 'logs', 'bootstrap'] as $path) {
            mkdir(self::$runtime.'/'.$path, 0700, true);
        }
        // Process-local overrides: never delete or load bootstrap/cache/config.php.
        $settings = array_merge($settings, [
            'APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'APP_BASE_PATH' => $root,
            'APP_CONFIG_CACHE' => $cacheRoot.'/config.php',
            'APP_ROUTES_CACHE' => $cacheRoot.'/routes.php',
            'APP_EVENTS_CACHE' => $cacheRoot.'/events.php',
            'APP_SERVICES_CACHE' => $cacheRoot.'/services.php',
            'APP_PACKAGES_CACHE' => $cacheRoot.'/packages.php',
            'VIEW_COMPILED_PATH' => self::$runtime.'/framework/views',
            'LARAVEL_STORAGE_PATH' => self::$runtime,
            'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array',
            'MAIL_MAILER' => 'array', 'QUEUE_CONNECTION' => 'sync',
            'FILESYSTEM_DISK' => 'local', 'LOG_CHANNEL' => 'null',
            'DB_URL' => '', 'DATABASE_URL' => '',
        ]);
        foreach ($settings as $name => $value) {
            putenv($name.'='.$value);
            $_ENV[$name] = $_SERVER[$name] = $value;
        }
        self::$settings = $settings;
        register_shutdown_function(function () use ($root): void {
            $real = realpath(self::$runtime);
            $parent = realpath($root.'/storage/app/testing');
            if ($real && $parent && dirname($real) === $parent && str_starts_with(basename($real), 'runtime-') && ! is_link($real)) {
                self::removeRuntime($real);
            }
        });
    }

    public static function validate(array $settings, array $approved): void
    {
        $database = $settings['DB_DATABASE'] ?? '';
        if (($settings['APP_ENV'] ?? '') !== 'testing' || ($settings['DB_CONNECTION'] ?? '') !== 'mysql'
            || ! preg_match('/^[a-zA-Z0-9_]+_test(?:ing)?$/', $database)
            || $database === ($approved['source_database'] ?? null)
            || $database !== ($approved['database'] ?? null)
            || ($settings['DB_USERNAME'] ?? '') !== ($approved['username'] ?? null)
            || ($settings['DB_HOST'] ?? '') !== ($approved['host'] ?? null)
            || (string) ($settings['DB_PORT'] ?? '') !== (string) ($approved['port'] ?? null)
            || ($settings['WPITV_TEST_DATABASE_ISOLATED'] ?? '') !== 'true'
            || ! empty($settings['DB_URL']) || ! empty($settings['DATABASE_URL'])) {
            throw new RuntimeException('TEST SAFETY ABORT: unapproved testing environment or database identity.');
        }
        $key = $settings['APP_KEY'] ?? '';
        if (! str_starts_with($key, 'base64:') || strlen((string) base64_decode(substr($key, 7), true)) !== 32) {
            throw new RuntimeException('TEST SAFETY ABORT: a separate valid testing key is required.');
        }
    }

    public static function runtime(): string
    {
        self::initialize();

        return self::$runtime;
    }

    public static function configure(Application $app): void
    {
        $connection = $app['config']->get('database.connections.mysql');
        if (! $app->environment('testing') || $app->configurationIsCached()
            || $app['config']->get('database.default') !== 'mysql'
            || $connection['database'] !== self::$settings['DB_DATABASE']
            || $connection['username'] !== self::$settings['DB_USERNAME']
            || $connection['host'] !== self::$settings['DB_HOST']
            || (string) $connection['port'] !== self::$settings['DB_PORT']
            || ! empty($connection['url'])) {
            throw new RuntimeException('TEST SAFETY ABORT: Laravel resolved unsafe configuration.');
        }
        // Remove all fallback connections/disks, not just the default selections.
        $app['config']->set('database.connections', ['mysql' => $connection]);
        $app['config']->set('filesystems.disks', [
            'local' => ['driver' => 'local', 'root' => self::$runtime.'/app/private', 'throw' => true],
            'public' => ['driver' => 'local', 'root' => self::$runtime.'/app/public', 'url' => 'http://localhost/storage', 'visibility' => 'public', 'throw' => true],
        ]);
        $app['config']->set([
            'filesystems.links' => [], 'filesystems.default' => 'local',
            'livewire.temporary_file_upload.disk' => 'local',
            'cache.default' => 'array', 'session.driver' => 'array',
            'mail.default' => 'array', 'queue.default' => 'sync',
        ]);
    }

    private static function removeRuntime(string $path): void
    {
        foreach (new \FilesystemIterator($path, \FilesystemIterator::SKIP_DOTS) as $file) {
            if ($file->isDir() && ! $file->isLink()) {
                self::removeRuntime($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        // Windows may retain compiled PHP handles until shutdown completes.
        // A deferred removal of this private runtime directory must not append
        // an error response to an otherwise successful browser/Livewire request.
        @rmdir($path);
    }
}
