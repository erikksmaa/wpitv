<?php

namespace Tests\Feature\Public;

use App\Models\LiveStream;
use App\Models\Video;
use App\Models\WpitvAgenda;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LiveStreamingPublicTest extends TestCase
{
    public function test_named_routes_are_public_and_unique(): void
    {
        $this->assertSame('/live', route('live.index', [], false));
        $this->assertSame('/live/123', route('live.show', 123, false));
        foreach (['live.index', 'live.show'] as $name) {
            $routes = collect(app('router')->getRoutes()->getRoutes())->filter(fn ($route) => $route->getName() === $name);
            $this->assertCount(1, $routes);
            $this->assertNotContains('auth', $routes->first()->gatherMiddleware());
        }
        $this->get('/live')->assertOk();
    }

    private function stream(array $agenda = [], array $live = []): LiveStream
    {
        $this->assertSame('wpitv_rebuild_testing', DB::selectOne('SELECT DATABASE() AS name')->name);
        $record = WpitvAgenda::create(array_replace(['title' => 'Public Live Fixture', 'type' => 'live', 'visibility' => 'public', 'status' => 'scheduled', 'agenda_date' => now('Asia/Jakarta')->addDay()->toDateString(), 'start_time' => '23:00', 'end_time' => '01:00'], $agenda));
        $stream = new LiveStream;
        $stream->forceFill(array_replace(['agenda_id' => $record->id, 'youtube_id' => substr(bin2hex(random_bytes(6)), 0, 11), 'status' => 'scheduled', 'version' => 1, 'status_changed_at' => now('UTC')], $live))->save();

        return $stream;
    }

    public function test_internal_draft_and_inconsistent_pairs_are_404(): void
    {
        foreach ([['visibility' => 'internal'], ['status' => 'draft'], ['status' => 'completed'], ['type' => 'meeting']] as $agenda) {
            $stream = $this->stream($agenda, ['youtube_id' => null]);
            $this->get(route('live.show', $stream->id))->assertNotFound();
        }
    }

    public function test_public_detail_safe_embed_no_autoplay_no_private_payload(): void
    {
        $stream = $this->stream(['description' => '<script>alert(1)</script>'], ['youtube_id' => 'AbC_dE-1234']);
        $this->get(route('live.show', $stream->id))->assertOk()->assertSee('youtube-nocookie.com/embed/AbC_dE-1234', false)->assertSee('autoplay=0', false)->assertSee('(+1 hari) WIB')->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('status_changed_by')->assertDontSee('before_values')->assertDontSee('actor_name');
    }

    public function test_missing_or_invalid_id_never_creates_iframe(): void
    {
        $stream = $this->stream([], ['youtube_id' => null]);
        $this->get(route('live.show', $stream->id))->assertOk()->assertSee('Tautan YouTube belum tersedia')->assertDontSee('<iframe', false);
        DB::table('live_streams')->where('id', $stream->id)->update(['youtube_id' => '!!!!!!!!!!!']);
        $this->get(route('live.show', $stream->id))->assertOk()->assertDontSee('<iframe', false);
    }

    public function test_past_schedule_separate_from_future_and_multiple_live_supported(): void
    {
        $this->stream(['title' => 'Past scheduled', 'agenda_date' => now('Asia/Jakarta')->subDay()->toDateString()]);
        $this->stream(['title' => 'Future scheduled'], ['youtube_id' => null]);
        $this->stream(['title' => 'Live one'], ['status' => 'live', 'live_marked_at' => now('UTC'), 'youtube_id' => 'abc12345678']);
        $this->stream(['title' => 'Live two'], ['status' => 'live', 'live_marked_at' => now('UTC'), 'youtube_id' => 'xyz12345678']);
        $this->get(route('live.index'))->assertOk()->assertSeeInOrder(['Sedang Live', 'Live one', 'Live two', 'Jadwal Mendatang', 'Future scheduled', 'Menunggu Pembaruan Operator', 'Past scheduled']);
    }

    public function test_archive_requires_exact_video_and_completed_public_agenda(): void
    {
        $stream = $this->stream(['status' => 'completed'], ['youtube_id' => 'AbC_dE-1234', 'status' => 'ended', 'ended_marked_at' => now('UTC')]);
        $this->get(route('live.show', $stream->id))->assertOk()->assertSee('Rekaman belum siap');
        $video = Video::create(['title' => 'Existing archive', 'youtube_id' => $stream->youtube_id, 'air_date' => today(), 'user_id' => $this->contentAuthor()->id]);
        DB::table('live_streams')->where('id', $stream->id)->update(['archive_video_id' => $video->id]);
        $this->get(route('live.show', $stream->id))->assertSee(route('video.show', $video->id), false);
        $this->get(route('live.index'))->assertOk()->assertSee('Public Live Fixture');
        $video->update(['youtube_id' => strtolower($stream->youtube_id)]);
        $this->get(route('live.show', $stream->id))->assertSee('Rekaman belum siap')->assertDontSee(route('video.show', $video->id), false);
        $this->get(route('live.index'))->assertDontSee('Public Live Fixture');
    }

    public function test_agenda_cta_and_pagination_and_private_state_rechecked(): void
    {
        $stream = $this->stream();
        $this->get(route('agenda.index'))->assertOk()->assertSee(route('live.show', $stream->id), false);
        foreach (range(1, 7) as $i) {
            $this->stream(['title' => 'Paginated '.$i], ['youtube_id' => null]);
        }
        $this->get(route('live.index'))->assertOk()->assertDontSee('Paginated 7');
        $this->get('/live?upcomingPage=2')->assertOk()->assertSee('Paginated 7');
        DB::table('wpitv_agendas')->where('id', $stream->agenda_id)->update(['visibility' => 'internal']);
        $this->get(route('live.show', $stream->id))->assertNotFound();
    }

    public function test_cancelled_detail_has_no_player_and_is_excluded_from_lists(): void
    {
        $stream = $this->stream(['status' => 'cancelled'], ['status' => 'cancelled', 'cancelled_marked_at' => now('UTC')]);
        $this->get(route('live.show', $stream->id))->assertOk()->assertSee('Siaran dibatalkan')->assertDontSee('<iframe', false)->assertDontSee('Buka di YouTube');
        $this->get(route('live.index'))->assertOk()->assertDontSee('Public Live Fixture');
    }
}
