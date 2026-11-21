<?php

namespace App\Services;

use App\Exceptions\LiveStreamOperationException;
use App\Models\LiveStream;
use App\Models\User;
use App\Support\LiveStreamErrors;
use Illuminate\Support\Facades\Gate;

class LiveDashboardMetrics
{
    public function get(User $user): ?array
    {
        // Must precede even constructing the query: editorial/club load none.
        if (! Gate::forUser($user)->allows('viewAny', LiveStream::class)) {
            return null;
        }
        try {
            return LiveStreamErrors::run(function () {
                $future = LiveStream::START_WIB.' > ?';
                $now = now('Asia/Jakarta')->format('Y-m-d H:i:s');
                $base = LiveStream::query()->leftJoin('wpitv_agendas as schedule', 'schedule.id', '=', 'live_streams.agenda_id')
                    ->where(fn ($sources) => $sources->whereNull('live_streams.agenda_id')->orWhere('schedule.type', 'live'));
                $scheduled = "(live_streams.agenda_id IS NULL OR schedule.status = 'scheduled')";
                $completed = "(live_streams.agenda_id IS NULL OR schedule.status = 'completed')";
                $visibility = LiveStream::VISIBILITY_SQL;
                $counts = (clone $base)->selectRaw("COALESCE(SUM(live_streams.status = 'live' AND $scheduled),0) AS on_air,
                    COALESCE(SUM(live_streams.status = 'scheduled' AND $scheduled AND $future),0) AS upcoming,
                    COALESCE(SUM(live_streams.status = 'ended' AND $completed AND NOT EXISTS (SELECT 1 FROM ms_video WHERE $visibility = 'public' AND ms_video.id = live_streams.archive_video_id AND BINARY ms_video.youtube_id = BINARY live_streams.youtube_id AND REGEXP_LIKE(CONVERT(live_streams.youtube_id USING utf8mb4), '^[A-Za-z0-9_-]{11}$', 'c'))),0) AS missing_archive", [$now])->first();
                $latest = (clone $base)->where('live_streams.status', 'scheduled')->whereRaw($scheduled)->whereRaw($future, [$now])
                    ->with(['agenda:id,title,agenda_date,start_time,end_time,type,program_id', 'agenda.program:id,name', 'manualProgram:id,name'])
                    ->orderByRaw(LiveStream::START_WIB)->orderBy('live_streams.id')->limit(5)->get(['live_streams.id', 'live_streams.agenda_id', 'live_streams.status', 'live_streams.manual_title', 'live_streams.manual_program_id', 'live_streams.manual_starts_at', 'live_streams.manual_ends_at']);

                return ['available' => true, 'on_air' => (int) $counts->on_air, 'upcoming' => (int) $counts->upcoming, 'missing_archive' => (int) $counts->missing_archive, 'latest' => $latest];
            });
        } catch (LiveStreamOperationException) {
            return ['available' => false];
        }
    }
}
