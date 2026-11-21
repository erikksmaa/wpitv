<?php

namespace App\Services;

use App\Exceptions\LiveStreamOperationException;
use App\Models\LiveStream;
use App\Support\LiveStreamErrors;
use App\Support\PublicLiveStreams;

class LiveStreamHighlights
{
    public function get(?int $programId = null): array
    {
        try {
            return LiveStreamErrors::run(function () use ($programId) {
                $base = PublicLiveStreams::query()->leftJoin('wpitv_agendas as schedule', 'schedule.id', '=', 'live_streams.agenda_id');
                if ($programId !== null) {
                    $base->whereRaw(LiveStream::PROGRAM_SQL.' = ?', [$programId]);
                }
                $base->orderByRaw(LiveStream::START_WIB)->orderBy('live_streams.id');
                $live = (clone $base)->where('live_streams.status', 'live');
                $upcoming = (clone $base)->where('live_streams.status', 'scheduled')->whereRaw(LiveStream::START_WIB.' > ?', [now('Asia/Jakarta')->format('Y-m-d H:i:s')]);
                if ($programId === null) {
                    $items = $live->limit(6)->get();

                    return ['available' => true, 'groups' => [$items->isEmpty() ? 'Jadwal Live Terdekat' : 'Sedang Live' => $items->isEmpty() ? $upcoming->limit(3)->get() : $items]];
                }

                return ['available' => true, 'groups' => [
                    'Sedang Live' => $live->paginate(6, ['*'], 'programLivePage'),
                    'Jadwal Live Mendatang' => $upcoming->paginate(6, ['*'], 'programSchedulePage'),
                    'Arsip Live' => PublicLiveStreams::archive(clone $base)->whereRaw("REGEXP_LIKE(CONVERT(live_streams.youtube_id USING utf8mb4), '^[A-Za-z0-9_-]{11}$', 'c')")->paginate(6, ['*'], 'programArchivePage'),
                ]];
            });
        } catch (LiveStreamOperationException) {
            return ['available' => false, 'groups' => []];
        }
    }
}
