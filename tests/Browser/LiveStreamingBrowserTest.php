<?php

namespace Tests\Browser;

use App\Models\LiveStream;
use App\Models\WpitvAgenda;
use App\Services\LiveStreamLifecycle;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Explicit isolated browser QA. Reuses the existing loopback/fixture harness. */
class LiveStreamingBrowserTest extends EquipmentBrowserTest
{
    protected function setUpEquipmentFixtures(): void
    {
        $this->owned['live_agendas'] = [];
        parent::setUpEquipmentFixtures();
        $agenda = WpitvAgenda::create(['title' => $this->prefix.' Live QA', 'type' => 'live', 'agenda_date' => now('Asia/Jakarta')->addDays(2)->toDateString(), 'start_time' => '23:00', 'end_time' => '01:00', 'status' => 'scheduled', 'visibility' => 'internal', 'created_by' => $this->actor()->id]);
        $this->own('live_agendas', $agenda->id);
        app(LiveStreamLifecycle::class)->create($this->actor(), $agenda->id);
        if (getenv('WPITV_LIVE_DUAL_QA') === 'true') {
            $availableAgenda = WpitvAgenda::create([
                'title' => $this->prefix.' Unlinked Live', 'type' => 'live',
                'agenda_date' => now('Asia/Jakarta')->addDays(3)->toDateString(),
                'start_time' => '19:00', 'status' => 'scheduled', 'visibility' => 'public',
                'created_by' => $this->actor()->id,
            ]);
            $this->own('live_agendas', $availableAgenda->id);
        }
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
        $streams = LiveStream::whereIn('agenda_id', $ids)->pluck('id')->all();
        if (getenv('WPITV_LIVE_DUAL_QA') === 'true') {
            $manual = LiveStream::whereNull('agenda_id')->whereIn('created_by', $users)->get();
            foreach ($manual as $stream) {
                if (! str_starts_with($stream->manual_title, $this->prefix)) {
                    throw new RuntimeException('LIVE_QA_CLEANUP_SCOPE');
                }
            }
            $streams = [...$streams, ...$manual->modelKeys()];
        }
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
        parent::cleanUpEquipmentFixtures();
    }
}
