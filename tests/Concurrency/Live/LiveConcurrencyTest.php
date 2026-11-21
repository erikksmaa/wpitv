<?php

namespace Tests\Concurrency\Live;

use App\Models\LiveStream;
use App\Models\User;
use App\Models\Video;
use App\Models\WpitvAgenda;
use App\Services\LiveStreamLifecycle;
use App\Support\LiveStreamState;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\LiveScenarioTestCase;

class LiveConcurrencyTest extends LiveScenarioTestCase
{
    public function test_two_requests_create_one_live_for_the_same_agenda(): void
    {
        $actor = $this->actor();
        $agenda = WpitvAgenda::create([
            'title' => $this->prefix.' race creation', 'type' => 'live', 'agenda_date' => now('Asia/Jakarta')->addDay()->toDateString(),
            'start_time' => '10:00', 'status' => 'scheduled', 'visibility' => 'internal', 'created_by' => $actor->id,
        ]);
        $this->own('live_agendas', $agenda->id);
        $job = ['operation' => 'create', 'agenda_id' => $agenda->id, 'owner_id' => $actor->id];
        $results = $this->race([
            [...$job, 'actor_id' => $actor->id],
            [...$job, 'actor_id' => $this->actor(User::ROLE_SUPER_ADMIN)->id],
        ], 'wpitv_agendas', $agenda->id);
        $this->assertCount(1, array_filter($results, fn ($result) => $result['ok']));
        $this->assertContains('LIVE_DUPLICATE', array_column($results, 'code'));
        $this->assertSame(1, LiveStream::where('agenda_id', $agenda->id)->sole()->events()->count());
    }

    public function test_concurrent_manual_updates_lock_only_the_stream(): void
    {
        $actor = $this->actor();
        $stream = app(LiveStreamLifecycle::class)->create($actor, null, null, 'manual', [
            'title' => $this->prefix.' manual', 'agenda_date' => now('Asia/Jakarta')->addDay()->toDateString(),
            'start_time' => '10:00', 'visibility' => 'internal',
        ]);
        $this->own('manual_live_streams', $stream->id);
        $job = ['stream_id' => $stream->id, 'owner_id' => $actor->id, 'version' => 1];
        $results = $this->race([
            [...$job, 'operation' => 'config', 'actor_id' => $actor->id],
            [...$job, 'operation' => 'status', 'actor_id' => $this->actor(User::ROLE_SUPER_ADMIN)->id, 'target' => 'cancelled'],
        ], 'live_streams', $stream->id);
        $this->assertCount(1, array_filter($results, fn ($result) => $result['ok']));
        $this->assertContains('LIVE_STALE', array_column($results, 'code'));
        $this->assertSame([['live_streams'], ['live_streams']], array_column($results, 'locks'));
        $this->assertSame(2, $stream->fresh()->version);
        $this->assertSame(2, $stream->events()->count());
    }

    public static function races(): array
    {
        return [['status', 'status'], ['agenda', 'status'], ['status', 'config'], ['archive', 'archive']];
    }

    #[DataProvider('races')]
    public function test_concurrent_operators_commit_one_atomic_change(string $first, string $second): void
    {
        $s = LiveStream::whereIn('agenda_id', $this->owned['live_agendas'])->firstOrFail();
        $version = 1;
        $videoId = null;
        if ($first === 'archive') {
            $s = app(LiveStreamLifecycle::class)->transition($this->actor(), $s->id, 1, 'ended', 'Synthetic administrative ending');
            $version = $s->version;
            $videoId = Video::create(['title' => $this->prefix.' archive', 'youtube_id' => $s->youtube_id, 'air_date' => today(), 'user_id' => $this->actor()->id])->id;
        }
        $beforeEvents = $s->events()->count();
        $jobs = [];
        foreach ([$first, $second] as $i => $op) {
            $jobs[] = ['operation' => $op, 'stream_id' => $s->id, 'owner_id' => $this->actor()->id, 'actor_id' => $this->actor($i === 0 ? User::ROLE_PRODUCTION : User::ROLE_SUPER_ADMIN)->id, 'version' => $version, 'target' => $first === 'status' && $second === 'config' ? 'cancelled' : 'live', 'title' => $this->prefix.' concurrent edit', 'video_id' => $videoId];
        }
        $results = $this->race($jobs, 'wpitv_agendas', $s->agenda_id);
        $this->assertCount(1, array_filter($results, fn ($r) => $r['ok']));
        $loser = array_values(array_filter($results, fn ($r) => ! $r['ok']))[0];
        $this->assertSame('LIVE_STALE', $loser['code']);
        foreach ($results as $result) {
            $expected = $first === 'archive' && $result['ok'] ? ['wpitv_agendas', 'live_streams', 'ms_video'] : ['wpitv_agendas', 'live_streams'];
            $this->assertSame($expected, $result['locks']);
        }
        $s->refresh();
        $this->assertSame($version + 1, $s->version);
        $this->assertSame($beforeEvents + 1, $s->events()->count());
        LiveStreamState::assertConsistent($s, $s->agenda()->firstOrFail());
        if ($first === 'agenda') {
            $this->assertSame($results[0]['ok'] ? $this->prefix.' concurrent edit' : $this->prefix.' Live QA', $s->agenda->title);
            $this->assertSame($results[0]['ok'] ? 'scheduled' : 'live', $s->status);
        }
        if ($second === 'config') {
            $this->assertSame($results[0]['ok'] ? 'cancelled' : 'scheduled', $s->status);
            $this->assertSame($results[0]['ok'] ? 'AbC_dE-1234' : 'Zyx_1234567', $s->youtube_id);
        }
        $this->assertSame(0, $this->primary->transactionLevel());
    }
}
