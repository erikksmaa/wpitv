<?php

namespace Tests\Feature\Public;

use App\Models\LiveStream;
use App\Models\Program;
use App\Models\Role;
use App\Models\User;
use App\Models\Video;
use App\Models\WpitvAgenda;
use App\Services\LiveDashboardMetrics;
use App\Services\LiveStreamHighlights;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LiveHighlightsTest extends TestCase
{
    private function stream(array $agenda = [], string $status = 'scheduled'): LiveStream
    {
        $a = WpitvAgenda::create(array_replace(['title' => 'Highlights '.bin2hex(random_bytes(4)), 'type' => 'live', 'status' => $status === 'ended' ? 'completed' : 'scheduled', 'visibility' => 'public', 'agenda_date' => now('Asia/Jakarta')->addDay()->toDateString(), 'start_time' => '23:00', 'end_time' => '01:00'], $agenda));
        $s = new LiveStream;
        $s->forceFill(['agenda_id' => $a->id, 'youtube_id' => 'AbC'.bin2hex(random_bytes(4)), 'status' => $status, 'version' => 1, 'status_changed_at' => now('UTC'), 'live_marked_at' => $status === 'live' ? now('UTC') : null, 'ended_marked_at' => $status === 'ended' ? now('UTC') : null])->save();

        return $s;
    }

    public function test_home_empty_upcoming_multiple_live_and_no_hero_cache_dependency(): void
    {
        $service = app(LiveStreamHighlights::class);
        $this->assertTrue(collect($service->get()['groups'])->first()->isEmpty());
        $scheduled = $this->stream();
        $this->get('/')->assertOk()->assertSee($scheduled->agenda->title);
        $one = $this->stream(['title' => 'On air one'], 'live');
        $this->assertSame([$one->id], collect($service->get()['groups'])->first()->modelKeys());
        $this->get('/')->assertOk()->assertSee('On air one')->assertDontSee('On air two');
        $two = $this->stream(['title' => 'On air two'], 'live');
        $items = collect($service->get()['groups'])->first();
        $this->assertSame([$one->id, $two->id], $items->modelKeys());
        $this->get('/')->assertOk()->assertSee('On air one')->assertSee('On air two');
        $source = file_get_contents(app_path('Livewire/Public/Home.php'));
        $this->assertGreaterThan(strpos($source, 'Cache::remember'), strpos($source, "['liveHighlights']"));
    }

    public function test_program_public_filter_archive_exact_match_and_existing_videos(): void
    {
        $p = Program::create(['name' => 'Highlights program', 'slug' => 'highlights-program', 'status' => true]);
        $this->get('/program/'.$p->slug)->assertOk()->assertSee('Belum ada siaran');
        $live = $this->stream(['program_id' => $p->id, 'title' => 'Program live'], 'live');
        $this->stream(['program_id' => $p->id, 'title' => 'Secret internal', 'visibility' => 'internal']);
        $this->stream(['program_id' => $p->id, 'title' => 'Secret draft', 'status' => 'draft']);
        $ended = $this->stream(['program_id' => $p->id], 'ended');
        $v = Video::create(['title' => 'Existing archive highlights', 'youtube_id' => $ended->youtube_id, 'air_date' => today(), 'user_id' => $this->contentAuthor()->id, 'program_id' => $p->id]);
        DB::table('live_streams')->where('id', $ended->id)->update(['archive_video_id' => $v->id]);
        $this->get('/program/'.$p->slug)->assertOk()->assertSee('Program live')->assertSee('Tonton arsip Tayangan')->assertSee(route('video.show', $v->id), false)->assertDontSee('Secret internal')->assertDontSee('Secret draft');
        $v->update(['youtube_id' => strtolower($ended->youtube_id)]);
        $this->assertSame(0, app(LiveStreamHighlights::class)->get($p->id)['groups']['Arsip Live']->total());
    }

    public function test_dashboard_four_roles_counts_limit_query_budget_and_archive_mismatch(): void
    {
        $this->stream([], 'live');
        foreach (range(1, 7) as $i) {
            $this->stream();
        }
        $ended = $this->stream([], 'ended');
        $service = app(LiveDashboardMetrics::class);
        foreach (User::ADMIN_ROLES as $role) {
            $actor = User::factory()->create(['role_id' => Role::where('name', $role)->firstOrFail()->id, 'status' => true])->load('role');
            DB::enableQueryLog();
            DB::flushQueryLog();
            $data = $service->get($actor);
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();
            if (in_array($role, [User::ROLE_SUPER_ADMIN, User::ROLE_PRODUCTION])) {
                $this->assertTrue($data['available']);
                $this->assertSame(1, $data['on_air']);
                $this->assertSame(7, $data['upcoming']);
                $this->assertSame(1, $data['missing_archive']);
                $this->assertCount(5, $data['latest']);
                $this->assertLessThanOrEqual(4, $count);
                $this->actingAs($actor)->get('/admin/dashboard')->assertOk()->assertSee('Lima Jadwal Live Terdekat');
            } else {
                $this->assertNull($data);
                $this->assertSame(0, $count);
            }
        }
        $actor = $this->contentAuthor();
        $admin = User::factory()->create(['role_id' => Role::where('name', User::ROLE_SUPER_ADMIN)->firstOrFail()->id, 'status' => true])->load('role');
        $v = Video::create(['title' => 'Valid archive', 'youtube_id' => $ended->youtube_id, 'air_date' => today(), 'user_id' => $actor->id]);
        DB::table('live_streams')->where('id', $ended->id)->update(['archive_video_id' => $v->id]);
        $this->assertSame(0, $service->get($admin)['missing_archive']);
        $v->update(['youtube_id' => strtolower($ended->youtube_id)]);
        $this->assertSame(1, $service->get($admin)['missing_archive']);
    }
}
