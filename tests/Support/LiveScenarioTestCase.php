<?php

namespace Tests\Support;

use App\Models\LiveStream;
use App\Models\WpitvAgenda;
use App\Services\LiveStreamLifecycle;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Committed Live fixtures and multiprocess races, with existing strict DB guards. */
abstract class LiveScenarioTestCase extends EquipmentScenarioTestCase
{
    protected function setUpEquipmentFixtures(): void
    {
        $this->owned['live_agendas'] = [];
        $this->owned['manual_live_streams'] = [];
        parent::setUpEquipmentFixtures();
        $agenda = WpitvAgenda::create(['title' => $this->prefix.' Live QA', 'type' => 'live', 'agenda_date' => now('Asia/Jakarta')->addDays(2)->toDateString(), 'start_time' => '23:00', 'end_time' => '01:00', 'status' => 'scheduled', 'visibility' => 'public', 'created_by' => $this->actor()->id]);
        $this->own('live_agendas', $agenda->id);
        app(LiveStreamLifecycle::class)->create($this->actor(), $agenda->id, 'AbC_dE-1234');
    }

    protected function cleanUpEquipmentFixtures(): void
    {
        $users = $this->owned['users'];
        $agendas = WpitvAgenda::whereIn('created_by', $users)->get();
        foreach ($agendas as $agenda) {
            if (! str_starts_with($agenda->title, $this->prefix)) {
                throw new RuntimeException('LIVE_QA_CLEANUP_SCOPE');
            }
        }
        $ids = $agendas->modelKeys();
        $streams = [...LiveStream::whereIn('agenda_id', $ids)->pluck('id')->all(), ...$this->owned['manual_live_streams']];
        foreach (LiveStream::whereIn('id', $streams)->get() as $stream) {
            if (! in_array($stream->created_by, $users, true)) {
                throw new RuntimeException('LIVE_QA_ACTOR_SCOPE');
            }
        }
        // Exact synthetic IDs, after the harness rechecks DB identity/privileges.
        DB::transaction(function () use ($streams, $ids) {
            DB::table('live_stream_events')->whereIn('live_stream_id', $streams)->delete();
            DB::table('live_streams')->whereIn('id', $streams)->delete();
            DB::table('wpitv_agendas')->whereIn('id', $ids)->delete();
        });
        DB::table('ms_video')->whereIn('user_id', $users)->where('title', $this->prefix.' archive')->delete();
        parent::cleanUpEquipmentFixtures();
    }

    /** Both workers reach the actual locking-query callback while parent holds the target row. */
    protected function race(array $jobs, string $table, int $id): array
    {
        if (! in_array($table, ['wpitv_agendas', 'live_streams'], true)) {
            throw new RuntimeException('EQCT_LOCK_TABLE');
        }
        $directory = storage_path('framework/testing/'.$this->prefix.'-'.bin2hex(random_bytes(4)));
        mkdir($directory, 0700, true);
        $paths = [];
        $this->primary->beginTransaction();
        try {
            $this->primary->table($table)->where('id', $id)->lockForUpdate()->first();
            foreach ($jobs as $i => $job) {
                $base = $directory.'/worker-'.$i;
                file_put_contents($base.'.json', json_encode([...$job, 'go_file' => $base.'.go'], JSON_THROW_ON_ERROR));
                $process = proc_open([PHP_BINARY, base_path('tests/Concurrency/Live/worker.php'), $base.'.json'], [0 => ['file', 'NUL', 'r'], 1 => ['file', $base.'.out', 'w'], 2 => ['file', $base.'.err', 'w']], $pipes, base_path(), null, ['bypass_shell' => true, 'create_new_console' => false]);
                if (! is_resource($process)) {
                    throw new RuntimeException('EQCT_SPAWN');
                }
                $this->processes[] = $process;
                $paths[] = $base;
            }
            $ready = array_map(fn ($path) => $this->waitFor($path, 'ready'), $paths);
            $this->assertNotSame((string) $ready[0]['connection_id'], (string) $ready[1]['connection_id']);
            foreach ($paths as $path) {
                file_put_contents($path.'.go', 'go');
            }
            foreach ($paths as $path) {
                $this->waitFor($path, 'lock_attempt');
                $this->assertFalse($this->hasPhase($path, 'result'));
            }
            $this->primary->commit();
            $results = array_map(fn ($path) => $this->waitFor($path, 'result'), $paths);

            return $results;
        } finally {
            if ($this->primary->transactionLevel() > 0) {
                $this->primary->rollBack();
            }
            $deadline = microtime(true) + 3;
            foreach ($this->processes as $process) {
                while (proc_get_status($process)['running'] && microtime(true) < $deadline) {
                    usleep(10000);
                }
            }
            $this->stopEquipmentWorkers();
        }
    }

    private function phases(string $base): array
    {
        clearstatcache();
        $text = is_file($base.'.out') ? file_get_contents($base.'.out') : '';
        $rows = [];
        foreach (explode("\n", trim($text)) as $line) {
            $row = json_decode($line, true);
            if (is_array($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    private function hasPhase(string $base, string $phase): bool
    {
        return in_array($phase, array_column($this->phases($base), 'phase'), true);
    }

    private function waitFor(string $base, string $phase): array
    {
        $deadline = microtime(true) + 15;
        do {
            foreach ($this->phases($base) as $row) {
                if ($row['phase'] === 'fatal') {
                    throw new RuntimeException('EQCT_WORKER_'.$row['code']);
                } if ($row['phase'] === $phase) {
                    return $row;
                }
            }
            usleep(10000);
        } while (microtime(true) < $deadline);
        throw new RuntimeException('EQCT_WORKER_TIMEOUT_'.$phase);
    }
}
