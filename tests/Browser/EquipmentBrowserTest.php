<?php

namespace Tests\Browser;

use Tests\Support\EquipmentScenarioTestCase;

/** Explicit interactive QA only; excluded from the automatic equipment suite. */
class EquipmentBrowserTest extends EquipmentScenarioTestCase
{
    public function test_isolated_browser_workflow(): void
    {
        $loan = $this->checkout([$this->units[0], $this->units[1]]);
        $product = $this->units[2];
        $child = $this->unitFixture();
        $other = $this->unitFixture();
        $this->primary->table('inventory_unit_members')->insert(['unit_id' => $child->id, 'parent_id' => $product->id]);
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $this->assertIsResource($socket);
        $port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);
        $baseUrl = 'http://127.0.0.1:'.$port;
        $directory = storage_path('framework/testing/browser');
        mkdir($directory, 0700, true);
        mkdir($directory.'/sessions', 0700, true);
        $config = $directory.'/config.json';
        $events = $directory.'/events.jsonl';
        $done = $directory.'/done';
        file_put_contents($config, json_encode(['base_url' => $baseUrl, 'category' => $this->category->id, 'sample_loan' => $loan->id, 'product' => $product->id, 'other' => $other->id, 'prefix' => $this->prefix, 'actors' => array_map(fn ($a) => $a->id, $this->actors), 'events' => $events, 'role' => 'Admin Production'], JSON_THROW_ON_ERROR));
        $environment = getenv();
        $environment['WPITV_QA_CONFIG'] = $config;
        $environment['WPITV_EQUIPMENT_PHOTO_QA'] = 'true';
        $process = proc_open([PHP_BINARY, '-S', '127.0.0.1:'.$port, '-t', base_path('public'), base_path('tests/Browser/router.php')], [0 => ['file', 'NUL', 'r'], 1 => ['file', $directory.'/server.out', 'w'], 2 => ['file', $directory.'/server.err', 'w']], $pipes, base_path(), $environment, ['bypass_shell' => true, 'create_new_console' => false]);
        $this->assertIsResource($process);
        $this->processes[] = $process;
        // Paths and synthetic labels only: never output account passwords.
        fwrite(STDOUT, PHP_EOL.json_encode(['qa_url' => $baseUrl.'/admin/inventory/units', 'prefix' => $this->prefix, 'control' => $directory]).PHP_EOL);
        $deadline = microtime(true) + 600;
        try {
            while (! is_file($done) && microtime(true) < $deadline) {
                clearstatcache();
                usleep(100000);
            }$this->assertFileExists($done);
        } finally {
            $this->stopEquipmentWorkers();
            if (is_file($events)) {
                foreach (explode("\n", trim(file_get_contents($events))) as $line) {
                    $row = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                    if ($row['table'] === 'tokens') {
                        $this->rememberToken($row['token']);
                    } else {
                        $this->own($row['table'], (int) $row['id']);
                    }
                }
            }
        }
    }
}
