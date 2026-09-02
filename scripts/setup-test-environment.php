<?php

use Illuminate\Contracts\Console\Kernel;

// Explicit schema-only provisioning. Never used automatically by the test suite.
require dirname(__DIR__).'/vendor/autoload.php';

$root = dirname(__DIR__);
$directory = $root.'/storage/app/testing';
$mode = $argv[1] ?? 'audit';
$target = 'wpitv_testing';
$testUser = 'wpitv_test_runner';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$settings = config('database.connections.'.config('database.default'));
$source = $settings['database'];
if (config('database.default') !== 'mysql' || ! in_array($settings['host'], ['127.0.0.1', 'localhost'], true)
    || $source === $target || ! preg_match('/^[a-zA-Z0-9_]+$/', $source)) {
    throw new RuntimeException('Refusing setup: expected a separate local MySQL source.');
}
$pdo = new PDO('mysql:host='.$settings['host'].';port='.$settings['port'].';dbname='.$source.';charset=utf8mb4', $settings['username'], $settings['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

function rows(PDO $pdo, string $sql, array $values = []): array
{
    $statement = $pdo->prepare($sql);
    $statement->execute($values);

    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

function schema(PDO $pdo, string $database): array
{
    $result = [];
    foreach (rows($pdo, 'SELECT TABLE_NAME, TABLE_TYPE, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=? ORDER BY TABLE_NAME', [$database]) as $table) {
        if ($table['TABLE_TYPE'] !== 'BASE TABLE' || $table['ENGINE'] !== 'InnoDB') {
            throw new RuntimeException('Non-InnoDB table or view needs manual schema review.');
        }
        $name = str_replace('`', '``', $table['TABLE_NAME']);
        $ddl = $pdo->query('SHOW CREATE TABLE `'.$database.'`.`'.$name.'`')->fetch(PDO::FETCH_NUM)[1];
        $ddl = str_replace('`'.$database.'`.', '', $ddl);
        $ddl = preg_replace('/ CHARACTER SET \w+(?= COLLATE )/', '', $ddl);
        $result[$table['TABLE_NAME']] = preg_replace('/ AUTO_INCREMENT=\d+/', '', $ddl);
    }

    return $result;
}

function sourceFingerprint(PDO $pdo, string $database): array
{
    $result = [];
    foreach (rows($pdo, 'SELECT TABLE_NAME, AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA=? ORDER BY TABLE_NAME', [$database]) as $table) {
        $qualified = '`'.$database.'`.`'.str_replace('`', '``', $table['TABLE_NAME']).'`';
        $result[$table['TABLE_NAME']] = [
            'count' => (int) $pdo->query('SELECT COUNT(*) FROM '.$qualified)->fetchColumn(),
            'auto_increment' => $table['AUTO_INCREMENT'],
            'checksum' => $pdo->query('CHECKSUM TABLE '.$qualified.' EXTENDED')->fetch(PDO::FETCH_NUM)[1],
        ];
    }

    return $result;
}

$schema = schema($pdo, $source);
$schemaSettings = rows($pdo, 'SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=?', [$source])[0];
$targetExists = rows($pdo, 'SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=?', [$target]) !== [];
if ($mode === 'audit') {
    echo json_encode(['source' => $source, 'target' => $target, 'target_exists' => $targetExists, 'tables' => array_keys($schema), 'settings' => $schemaSettings], JSON_PRETTY_PRINT).PHP_EOL;
    exit;
}

if (in_array($mode, ['baseline-files', 'verify-files'], true)) {
    $fingerprints = [];
    foreach (['storage/app/public', 'storage/app/private', 'storage/framework/cache', 'storage/framework/sessions', 'bootstrap/cache'] as $relative) {
        $path = $root.'/'.$relative;
        if (! is_dir($path)) {
            continue;
        }
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile() && ! $file->isLink()) {
                $fingerprints[$file->getPathname()] = hash_file('sha256', $file->getPathname());
            }
        }
    }
    ksort($fingerprints);
    if ($mode === 'baseline-files') {
        file_put_contents($directory.'/application-files-baseline.json', json_encode($fingerprints));
        echo 'Recorded application file fingerprints without copying file contents.'.PHP_EOL;
    } else {
        $unchanged = $fingerprints === json_decode(file_get_contents($directory.'/application-files-baseline.json'), true);
        echo 'Application uploads/cache/sessions/config unchanged: '.($unchanged ? 'yes' : 'NO').PHP_EOL;
        exit($unchanged ? 0 : 1);
    }
    exit;
}

if ($mode === 'export') {
    if (rows($pdo, 'SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=?', [$source]) !== []) {
        throw new RuntimeException('Triggers need explicit cross-schema/definer review before export.');
    }
    if (is_file($directory.'/wpitv-schema.sql')) {
        throw new RuntimeException('Schema export already exists; archive it explicitly before regenerating.');
    }
    if (! is_dir($directory)) {
        mkdir($directory, 0700, true);
    }
    $executable = $argv[2] ?? 'mysqldump';
    $process = proc_open([$executable, '--host='.$settings['host'], '--port='.$settings['port'], '--user='.$settings['username'], '--no-data', '--routines=false', '--triggers', '--skip-add-drop-table', '--skip-comments', '--skip-lock-tables', '--no-tablespaces', '--set-gtid-purged=OFF', $source], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root, array_merge(getenv(), ['MYSQL_PWD' => $settings['password']]));
    fclose($pipes[0]);
    $dump = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (proc_close($process) !== 0) {
        throw new RuntimeException('Schema-only export failed; no database was created.');
    }
    $dump = preg_replace('/ AUTO_INCREMENT=\d+/', '', $dump);
    if (preg_match('/^\s*(INSERT|REPLACE|UPDATE|DELETE|USE|DROP|CREATE DATABASE)\b/im', $dump)
        || stripos($dump, 'DEFINER=') !== false || str_contains($dump, '`'.$source.'`.')) {
        throw new RuntimeException('Export requires manual review: unexpected data, definer, or source reference.');
    }
    file_put_contents($directory.'/wpitv-schema.sql', $dump);
    file_put_contents($directory.'/source-baseline.json', json_encode(sourceFingerprint($pdo, $source), JSON_PRETTY_PRINT));
    echo 'Exported schema only: '.count($schema).' tables. No application rows copied.'.PHP_EOL;
    exit;
}

if (($mode === 'prepare' && ($argv[2] ?? '') === '--create='.$target)
    || ($mode === 'finish' && ($argv[2] ?? '') === '--target='.$target)) {
    if (($mode === 'prepare' && $targetExists) || ($mode === 'finish' && ! $targetExists)
        || is_file($root.'/.env.testing') || rows($pdo, 'SELECT User FROM mysql.user WHERE User=?', [$testUser]) !== []) {
        throw new RuntimeException('Refusing to overwrite existing database, test account, or .env.testing.');
    }
    $dump = file_get_contents($directory.'/wpitv-schema.sql');
    if (! $dump || preg_match('/^\s*(INSERT|REPLACE|UPDATE|DELETE|USE|DROP|CREATE DATABASE)\b/im', $dump)
        || stripos($dump, 'DEFINER=') !== false || str_contains($dump, '`'.$source.'`.')) {
        throw new RuntimeException('Missing or unsafe schema-only export.');
    }
    $charset = $schemaSettings['DEFAULT_CHARACTER_SET_NAME'];
    $collation = $schemaSettings['DEFAULT_COLLATION_NAME'];
    if (! preg_match('/^\w+$/', $charset) || ! preg_match('/^\w+$/', $collation)) {
        throw new RuntimeException('Unsafe schema settings.');
    }
    if ($mode === 'prepare') {
        $pdo->exec('CREATE DATABASE `'.$target.'` CHARACTER SET '.$charset.' COLLATE '.$collation);
        $pdo->exec('USE `'.$target.'`');
        $pdo->exec($dump);
    }
    if (schema($pdo, $target) !== $schema) {
        throw new RuntimeException('Schema mismatch: stop and review the new test database.');
    }
    foreach (array_keys($schema) as $table) {
        if ((int) $pdo->query('SELECT COUNT(*) FROM `'.$target.'`.`'.$table.'`')->fetchColumn() !== 0) {
            throw new RuntimeException('Refusing to provision credentials for a nonempty target.');
        }
    }
    $password = bin2hex(random_bytes(24));
    $account = $pdo->quote($testUser)."@'localhost'";
    $pdo->exec('CREATE USER '.$account.' IDENTIFIED BY '.$pdo->quote($password));
    $pdo->exec('GRANT SELECT, INSERT, UPDATE, DELETE ON `'.$target.'`.* TO '.$account);
    // Check the dedicated identity can connect before writing local configuration.
    $testPdo = new PDO('mysql:host='.$settings['host'].';port='.$settings['port'].';dbname='.$target, $testUser, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $env = file_get_contents($root.'/.env.testing.example');
    $env = str_replace(['GENERATE_A_SEPARATE_LOCAL_TEST_KEY', 'GENERATED_LOCAL_TEST_ONLY_PASSWORD', 'DB_PORT=3306', 'DB_HOST=127.0.0.1'], ['base64:'.base64_encode(random_bytes(32)), $password, 'DB_PORT='.$settings['port'], 'DB_HOST='.$settings['host']], $env);
    file_put_contents($root.'/.env.testing', $env, LOCK_EX);
    file_put_contents($directory.'/approved.json', json_encode(['source_database' => $source, 'database' => $target, 'host' => $settings['host'], 'port' => (string) $settings['port'], 'username' => $testUser, 'schema_hash' => hash('sha256', json_encode($schema)), 'tables' => array_keys($schema)], JSON_PRETTY_PRINT));
    echo 'Created '.$target.' with '.count($schema).' empty tables and a test-only DML account. Local secrets stored, not printed.'.PHP_EOL;
    exit;
}

if ($mode === 'verify') {
    $baseline = json_decode(file_get_contents($directory.'/source-baseline.json'), true);
    $manifest = json_decode(file_get_contents($directory.'/approved.json'), true);
    $currentFingerprint = sourceFingerprint($pdo, $source);
    $unchanged = $currentFingerprint === $baseline;
    $changedTables = array_keys(array_filter($currentFingerprint, fn ($fingerprint, $table) => $fingerprint !== ($baseline[$table] ?? null), ARRAY_FILTER_USE_BOTH));
    echo json_encode(['changed_application_tables' => $changedTables, 'legacy_banner_unchanged' => $currentFingerprint['ms_banner'] === $baseline['ms_banner']], JSON_PRETTY_PRINT).PHP_EOL;
    $sameSchema = schema($pdo, $target) === $schema;
    $manifestMatches = hash('sha256', json_encode($schema)) === $manifest['schema_hash'];
    $empty = true;
    foreach (array_keys($schema) as $table) {
        $empty = $empty && (int) $pdo->query('SELECT COUNT(*) FROM `'.$target.'`.`'.$table.'`')->fetchColumn() === 0;
    }
    echo json_encode(['application_database_unchanged' => $unchanged, 'schema_matches' => $sameSchema, 'schema_manifest_matches' => $manifestMatches, 'test_tables_empty' => $empty, 'tables' => count($schema)], JSON_PRETTY_PRINT).PHP_EOL;
    exit($unchanged && $sameSchema && $manifestMatches && $empty ? 0 : 1);
}

if ($mode === 'accept-source' && ($argv[2] ?? '') === '--target='.$target) {
    $manifest = json_decode(file_get_contents($directory.'/approved.json'), true);
    if (! $targetExists || schema($pdo, $target) !== $schema
        || hash('sha256', json_encode($schema)) !== ($manifest['schema_hash'] ?? null)) {
        throw new RuntimeException('Refusing baseline refresh: test schema or approved manifest differs from the application schema.');
    }
    foreach (array_keys($schema) as $table) {
        if ((int) $pdo->query('SELECT COUNT(*) FROM `'.$target.'`.`'.$table.'`')->fetchColumn() !== 0) {
            throw new RuntimeException('Refusing baseline refresh: test database is not empty.');
        }
    }
    file_put_contents($directory.'/source-baseline.json', json_encode(sourceFingerprint($pdo, $source), JSON_PRETTY_PRINT), LOCK_EX);
    echo 'Accepted the current application fingerprint after schema and empty-test-target verification.'.PHP_EOL;
    exit;
}

if ($mode === 'canary') {
    $env = Dotenv\Dotenv::parse(file_get_contents($root.'/.env.testing'));
    if ($env['DB_DATABASE'] !== $target || $env['DB_USERNAME'] !== $testUser) {
        throw new RuntimeException('Refusing canary: wrong target.');
    }
    $test = new PDO('mysql:host='.$env['DB_HOST'].';port='.$env['DB_PORT'].';dbname='.$target, $testUser, $env['DB_PASSWORD'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $name = 'ISOLATION_CANARY_'.bin2hex(random_bytes(8));
    $test->beginTransaction();
    try {
        $statement = $test->prepare('INSERT INTO roles (name, description, created_at, updated_at) VALUES (?, ?, NOW(), NOW())');
        $statement->execute([$name, 'Synthetic isolation canary']);
        $inTest = count(rows($test, 'SELECT id FROM roles WHERE name=?', [$name])) === 1;
        $inSource = rows($pdo, 'SELECT id FROM roles WHERE name=?', [$name]) !== [];
    } finally {
        $test->rollBack();
    }
    $removed = rows($test, 'SELECT id FROM roles WHERE name=?', [$name]) === [];
    $sourceAccessDenied = false;
    try {
        $test->query('SELECT id FROM `'.$source.'`.roles LIMIT 1');
    } catch (PDOException $exception) {
        $sourceAccessDenied = in_array((int) ($exception->errorInfo[1] ?? 0), [1044, 1142], true);
    }
    echo json_encode(['canary_in_test' => $inTest, 'canary_absent_from_application' => ! $inSource, 'canary_rolled_back' => $removed, 'test_account_denied_application_access' => $sourceAccessDenied], JSON_PRETTY_PRINT).PHP_EOL;
    exit($inTest && ! $inSource && $removed && $sourceAccessDenied ? 0 : 1);
}

throw new RuntimeException('Use audit, export [mysqldump-path], prepare --create=wpitv_testing, finish --target=wpitv_testing, canary, verify, accept-source --target=wpitv_testing, baseline-files, or verify-files.');
