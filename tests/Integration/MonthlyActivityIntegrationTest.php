<?php

namespace Tests\Integration;

use App\Exceptions\MonthlyActivityException;
use App\Models\MonthlyActivityEvent;
use App\Models\MonthlyActivityReport;
use App\Models\MonthlyActivityVersion;
use App\Models\User;
use App\Services\MonthlyActivityLifecycle;
use App\Support\MonthlyActivityRules;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use LogicException;
use RuntimeException;
use Tests\Support\ReleaseScenarioTestCase;

class MonthlyActivityIntegrationTest extends ReleaseScenarioTestCase
{
    protected function cleanUpReleaseFixtures(): void
    {
        $ids = MonthlyActivityReport::whereIn('created_by', $this->owned['users'])->pluck('id');
        $this->primary->transaction(function () use ($ids) {
            foreach (['events', 'versions', 'attachments', 'items'] as $table) {
                $this->primary->table('monthly_activity_'.$table)->whereIn('report_id', $ids)->delete();
            }
            $this->primary->table('monthly_activity_reports')->whereIn('id', $ids)->delete();
            $this->primary->table('ms_news')->whereIn('user_id', $this->owned['users'])->where('slug', strtolower($this->prefix))->delete();
        });
        parent::cleanUpReleaseFixtures();
    }

    private function service(): MonthlyActivityLifecycle
    {
        return app(MonthlyActivityLifecycle::class);
    }

    private function draft(): MonthlyActivityReport
    {
        return $this->service()->createDraft($this->actor(), 'production', '2026-11');
    }

    private function rejects(string $class, callable $operation, ?string $code = null): void
    {
        try {
            $operation();
            $this->fail('Unexpected success.');
        } catch (\Throwable $e) {
            $this->assertInstanceOf($class, $e);
            if ($code !== null) {
                $this->assertSame($code, $e->errorCode);
                $this->assertNull($e->getPrevious());
            }
        }
    }

    private function sqlRejects(int|array $errno, callable $operation): void
    {
        try {
            $operation();
            $this->fail('Constraint accepted invalid data.');
        } catch (QueryException $e) {
            $this->assertContains((int) $e->errorInfo[1], (array) $errno);
            $this->assertSame($errno === 3819 ? 'HY000' : '23000', $e->errorInfo[0]);
        }
    }

    private function item(MonthlyActivityReport $report, array $attributes = []): array
    {
        return array_replace(['report_id' => $report->id, 'activity_date' => '2026-11-30', 'title' => 'Synthetic activity', 'description' => 'Synthetic description', 'outcome' => 'Synthetic result', 'obstacles' => 'None', 'follow_up' => 'None', 'contribution' => 'Synthetic contribution', 'created_at' => '2026-11-30 16:59:59.123456', 'updated_at' => '2026-11-30 16:59:59.123456'], $attributes);
    }

    public function test_rbac_discovery_and_one_report_per_division_month(): void
    {
        $prod = $this->draft();
        $news = $this->actor(User::ROLE_NEWS);
        $admin = $this->actor(User::ROLE_SUPER_ADMIN);
        $club = $this->actor(User::ROLE_TVCLUB);
        $report = $this->service()->createDraft($news, 'news', '2026-11');
        $this->assertNotSame($prod->id, $report->id);
        $this->assertTrue(Gate::forUser($admin)->allows('view', $prod));
        $this->assertTrue(Gate::forUser($news)->allows('view', $report));
        $this->assertFalse(Gate::forUser($news)->allows('view', $prod));
        $this->assertFalse(Gate::forUser($club)->allows('viewAny', MonthlyActivityReport::class));
        foreach ([$news, $admin, $club] as $actor) {
            $this->rejects(AuthorizationException::class, fn () => $this->service()->createDraft($actor, 'production', '2026-12'));
        }
        $this->rejects(AuthorizationException::class, fn () => $this->service()->updateDraft($news, $prod->id, 1, 'Cross division'));
        $this->rejects(AuthorizationException::class, fn () => $this->service()->approve($this->actor(), $prod->id, 1));
        $this->rejects(MonthlyActivityException::class, fn () => $this->draft(), 'MONTHLY_DUPLICATE');
        $this->assertSame(1, $prod->events()->count());
    }

    public function test_period_division_date_and_nil_validation(): void
    {
        $actor = $this->actor();
        $this->rejects(ValidationException::class, fn () => $this->service()->createDraft($actor, 'production', '2026-13'));
        $this->rejects(ValidationException::class, fn () => $this->service()->createDraft($actor, 'invalid', '2026-11'));
        $report = $this->draft();
        $this->assertSame('2026-11-01', $report->fresh()->period_start->toDateString());
        $this->rejects(ValidationException::class, fn () => $this->service()->submit($actor, $report->id, 1));
        $this->primary->table('monthly_activity_items')->insert($this->item($report, ['activity_date' => '2026-12-01']));
        $this->rejects(ValidationException::class, fn () => $this->service()->submit($actor, $report->id, 1));
        $this->assertSame(1, $report->fresh()->version);
        $this->assertSame(0, $report->versions()->count());
        $this->assertSame('2026-11-30 17:00:00', MonthlyActivityRules::period('2026-12')->utc()->format('Y-m-d H:i:s'));
    }

    public function test_lifecycle_stale_history_and_json_round_trip(): void
    {
        $actor = $this->actor();
        $admin = $this->actor(User::ROLE_SUPER_ADMIN);
        $report = $this->draft();
        $id = $report->id;
        $this->service()->updateDraft($actor, $id, 1, ' First reason ');
        $this->rejects(MonthlyActivityException::class, fn () => $this->service()->updateDraft($actor, $id, 1, 'Stale'), 'MONTHLY_STALE');
        $this->assertSame(2, $this->service()->updateDraft($actor, $id, 2, 'First reason')->version);
        $this->service()->submit($actor, $id, 2);
        $first = $report->versions()->sole();
        $this->assertSame('First reason', $first->snapshot['nil_reason']);
        $this->assertSame($first->snapshot_hash, MonthlyActivityRules::snapshotHash($first->snapshot));
        $this->service()->requestRevision($admin, $id, 3, 'Clarify');
        $this->service()->updateDraft($actor, $id, 4, 'Revised reason');
        $this->service()->submit($actor, $id, 5);
        $approved = $this->service()->approve($admin, $id, 6);
        $this->assertSame('approved', $approved->status);
        $this->assertSame(7, $approved->version);
        $this->assertSame(7, $report->events()->count());
        $this->assertSame(2, $report->versions()->count());
        $this->assertSame('First reason', $first->fresh()->snapshot['nil_reason']);
        $this->assertSame(2, $report->events()->where('action', 'approved')->sole()->reportVersion->revision_number);
        $this->rejects(MonthlyActivityException::class, fn () => $this->service()->requestRevision($admin, $id, 7, 'Terminal'), 'MONTHLY_STATE');
        foreach ([$report, $first, $report->events()->firstOrFail()] as $model) {
            $this->rejects(LogicException::class, fn () => $model->saveQuietly());
            $this->rejects(LogicException::class, fn () => $model->deleteQuietly());
            $this->rejects(LogicException::class, fn () => $model->forceDelete());
        }
    }

    public function test_snapshot_and_audit_failures_roll_back(): void
    {
        $report = $this->draft();
        $actor = $this->actor();
        $this->service()->updateDraft($actor, $report->id, 1, 'No activities');
        foreach ([MonthlyActivityVersion::class, MonthlyActivityEvent::class] as $model) {
            $original = $model::getEventDispatcher();
            $model::setEventDispatcher(clone $original);
            try {
                $model::creating(fn () => false);
                $this->rejects(MonthlyActivityException::class, fn () => $this->service()->submit($actor, $report->id, 2), 'MONTHLY_FAILURE');
            } finally {
                $model::setEventDispatcher($original);
            }
            $this->assertSame('draft', $report->fresh()->status);
            $this->assertSame(2, $report->fresh()->version);
            $this->assertSame(0, $report->versions()->count());
            $this->assertSame(2, $report->events()->count());
        }
    }

    public function test_real_constraints_and_missing_source_snapshot(): void
    {
        $report = $this->draft();
        $news = $this->actor(User::ROLE_NEWS);
        $other = $this->service()->createDraft($news, 'news', '2026-11');
        $db = $this->primary;
        $this->sqlRejects(3819, fn () => $db->table('monthly_activity_reports')->where('id', $report->id)->update(['period_start' => '2026-11-02']));
        $this->sqlRejects(3819, fn () => $db->table('monthly_activity_reports')->where('id', $report->id)->update(['status' => 'invalid']));
        $this->sqlRejects([1216, 1452], fn () => $db->table('monthly_activity_items')->insert($this->item($report, ['report_id' => PHP_INT_MAX])));
        $this->sqlRejects(3819, fn () => $db->table('monthly_activity_items')->insert($this->item($report, ['source_type' => 'news'])));
        $sourceId = $db->table('ms_news')->insertGetId(['user_id' => $news->id, 'title' => 'Synthetic source', 'slug' => strtolower($this->prefix), 'content' => 'Synthetic only']);
        $item = $this->item($other, ['source_type' => 'news', 'source_id' => $sourceId]);
        $itemId = $db->table('monthly_activity_items')->insertGetId($item);
        $this->sqlRejects(1062, fn () => $db->table('monthly_activity_items')->insert($item));
        $db->table('monthly_activity_items')->insert([$this->item($other), $this->item($other)]);
        $file = ['report_id' => $other->id, 'item_id' => $itemId, 'display_name' => 'proof.pdf', 'disk' => 'local', 'storage_key' => 'synthetic', 'mime_type' => 'application/pdf', 'size_bytes' => 1, 'sha256' => str_repeat('a', 64), 'status' => 'ready', 'created_at' => $item['created_at'], 'updated_at' => $item['updated_at']];
        $this->sqlRejects([1216, 1452], fn () => $db->table('monthly_activity_attachments')->insert([...$file, 'report_id' => $report->id]));
        $this->sqlRejects(3819, fn () => $db->table('monthly_activity_attachments')->insert([...$file, 'size_bytes' => 0]));
        $db->table('monthly_activity_attachments')->insert($file);
        $this->sqlRejects(1062, fn () => $db->table('monthly_activity_attachments')->insert($file));
        $this->service()->submit($news, $other->id, 1);
        $snapshot = $other->versions()->sole();
        $db->table('ms_news')->where('id', $sourceId)->where('user_id', $news->id)->update(['title' => 'Changed source']);
        $this->assertSame($snapshot->snapshot, $snapshot->fresh()->snapshot);
        $db->table('ms_news')->where('id', $sourceId)->where('user_id', $news->id)->delete();
        $this->assertSame($snapshot->snapshot_hash, MonthlyActivityRules::snapshotHash($snapshot->fresh()->snapshot));
        $this->assertSame($sourceId, $snapshot->snapshot['items'][0]['source_id']);
        $this->assertSame(3, count($snapshot->snapshot['items']));
        $this->assertSame('2026-11-30 16:59:59.123456', $other->items()->findOrFail($itemId)->getRawOriginal('created_at'));
        $this->assertArrayNotHasKey('storage_key', $snapshot->snapshot['attachments'][0]);
        $event = ['report_id' => $report->id, 'report_version_id' => $snapshot->id, 'action' => 'submitted', 'to_status' => 'submitted', 'actor_name' => 'Synthetic', 'created_at' => $item['created_at']];
        $this->sqlRejects([1216, 1452], fn () => $db->table('monthly_activity_events')->insert($event));
        $this->sqlRejects(3819, fn () => $db->table('monthly_activity_events')->insert([...$event, 'report_id' => $other->id, 'action' => 'invalid']));
        $this->sqlRejects(3819, fn () => $db->table('monthly_activity_versions')->where('id', $snapshot->id)->update(['schema_version' => 0]));
    }

    public function test_two_workers_race_for_the_same_report_key(): void
    {
        $this->raceMonthlyWorkers(['operation' => 'create'], ['operation' => 'create'], 'MONTHLY_DUPLICATE');
        $report = MonthlyActivityReport::where('created_by', $this->actor()->id)->sole();
        $this->assertSame(1, $report->version);
        $this->assertSame(1, $report->events()->count());
    }

    public function test_submit_races_with_an_item_edit(): void
    {
        $report = $this->draft();
        $item = $this->item($report);
        $itemId = $this->primary->table('monthly_activity_items')->insertGetId($item);
        $job = ['report_id' => $report->id, 'version' => $report->version];
        $winner = $this->raceMonthlyWorkers(
            [...$job, 'operation' => 'submit'],
            [...$job, 'operation' => 'edit', 'item_id' => $itemId, 'activity' => [...$item, 'title' => 'Edited title']],
        );
        $this->assertSame(2, $report->fresh()->version);
        $this->assertSame(2, $report->events()->count());
        $this->assertSame($winner === 0 ? 'submitted' : 'draft', $report->fresh()->status);
        $this->assertSame($winner === 0 ? $item['title'] : 'Edited title', $report->items()->sole()->title);
        $this->assertSame($winner === 0 ? 1 : 0, $report->versions()->count());
        if ($winner === 0) {
            $this->assertSame($item['title'], $report->versions()->sole()->snapshot['items'][0]['title']);
        }
    }

    public function test_approve_races_with_a_revision_request(): void
    {
        $report = $this->draft();
        $report = $this->service()->updateDraft($this->actor(), $report->id, $report->version, 'Synthetic nil report');
        $report = $this->service()->submit($this->actor(), $report->id, $report->version);
        $snapshot = $report->versions()->sole();
        $job = ['report_id' => $report->id, 'version' => $report->version, 'actor_id' => $this->actor(User::ROLE_SUPER_ADMIN)->id];
        $winner = $this->raceMonthlyWorkers([...$job, 'operation' => 'approve'], [...$job, 'operation' => 'revise']);
        $this->assertSame($report->version + 1, $report->fresh()->version);
        $this->assertSame($winner === 0 ? 'approved' : 'revision', $report->fresh()->status);
        $event = $report->events()->whereIn('action', ['approved', 'revision_requested'])->sole();
        $this->assertSame($winner === 0 ? 'approved' : 'revision_requested', $event->action);
        $this->assertSame($snapshot->id, $event->report_version_id);
        $this->assertSame($snapshot->snapshot_hash, $report->versions()->sole()->snapshot_hash);
    }

    public function test_two_uploads_race_with_the_same_version_and_clean_the_losing_file(): void
    {
        $report = $this->draft();
        $job = ['report_id' => $report->id, 'version' => $report->version, 'operation' => 'upload'];
        $this->raceMonthlyWorkers($job, $job);
        $file = $report->attachments()->sole();
        $this->assertSame(2, $report->fresh()->version);
        $this->assertSame($file->id, $report->events()->where('action', 'updated')->sole()->changes['attachment_id']);
        $this->assertSame([$file->storage_key], Storage::disk('local')->allFiles('monthly-activity/'.$report->id));
        $this->assertSame(0, $report->versions()->count());
    }

    private function raceMonthlyWorkers(array $leftJob, array $rightJob, string $loser = 'MONTHLY_STALE'): int
    {
        $directory = storage_path('framework/testing/'.$this->prefix.'-monthly');
        mkdir($directory, 0700, true);
        if ($leftJob['operation'] === 'upload') {
            file_put_contents($directory.'/proof.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n%%EOF\n");
        }
        $paths = [];
        try {
            foreach ([$leftJob, $rightJob] as $i => $job) {
                $path = $directory.'/worker-'.$i;
                $paths[] = $path;
                file_put_contents($path.'.json', json_encode([...$job, 'actor_id' => $job['actor_id'] ?? $this->actor()->id, 'go_file' => $path.'.go'], JSON_THROW_ON_ERROR));
                $process = proc_open([PHP_BINARY, base_path('tests/Concurrency/Monthly/worker.php'), $path.'.json'], [0 => ['file', 'NUL', 'r'], 1 => ['file', $path.'.out', 'w'], 2 => ['file', $path.'.err', 'w']], $pipes, base_path(), null, ['bypass_shell' => true, 'create_new_console' => false]);
                if (! is_resource($process)) {
                    throw new RuntimeException('MONTHLY_WORKER_SPAWN');
                }
                $this->processes[] = $process;
            }
            $left = $this->waitForMonthly($paths[0], 'ready');
            $right = $this->waitForMonthly($paths[1], 'ready');
            $this->assertNotSame($left['connection_id'], $right['connection_id']);
            $this->assertSame($left['uuid'], $right['uuid']);
            $this->assertSame($this->primary->selectOne('SELECT @@server_uuid AS uuid')->uuid, $left['uuid']);
            foreach ($paths as $path) {
                file_put_contents($path.'.go', 'go');
            }
            $results = array_map(fn ($path) => $this->waitForMonthly($path, 'result')['code'], $paths);
            $this->assertEqualsCanonicalizing(['OK', $loser], $results);

            return array_search('OK', $results, true);
        } finally {
            $this->stopReleaseWorkers();
        }
    }

    private function waitForMonthly(string $path, string $phase): array
    {
        $deadline = microtime(true) + 30;
        do {
            clearstatcache();
            $lines = is_file($path.'.out') ? file($path.'.out', FILE_IGNORE_NEW_LINES) : [];
            foreach ($lines ?: [] as $line) {
                $message = json_decode($line, true);
                if (($message['phase'] ?? null) === 'fatal') {
                    throw new RuntimeException('MONTHLY_WORKER_FAILURE');
                }
                if (($message['phase'] ?? null) === $phase) {
                    return $message;
                }
            }
            usleep(10000);
        } while (microtime(true) < $deadline);
        throw new RuntimeException('MONTHLY_WORKER_TIMEOUT_'.$phase);
    }
}
