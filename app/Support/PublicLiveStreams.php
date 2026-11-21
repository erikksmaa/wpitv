<?php

namespace App\Support;

use App\Models\LiveStream;
use Illuminate\Database\Eloquent\Builder;

/** Public projection: never hydrate actors, audit, or operational reasons. */
final class PublicLiveStreams
{
    public static function query(): Builder
    {
        return LiveStream::publicVisible()
            ->select('live_streams.id', 'live_streams.agenda_id', 'live_streams.youtube_id', 'live_streams.status', 'live_streams.archive_video_id',
                'live_streams.manual_title', 'live_streams.manual_program_id', 'live_streams.manual_starts_at', 'live_streams.manual_ends_at', 'live_streams.manual_visibility')
            ->with([
                'agenda:id,title,description,type,visibility,status,agenda_date,start_time,end_time,program_id',
                'agenda.program' => fn ($q) => $q->where('status', true)->select('id', 'name', 'slug'),
                'manualProgram' => fn ($q) => $q->where('status', true)->select('id', 'name', 'slug'),
                'archiveVideo:id,title,youtube_id',
            ]);
    }

    public static function archive(Builder $query): Builder
    {
        return $query->where('live_streams.status', 'ended')->whereHas('archiveVideo', fn ($q) => $q->whereRaw('BINARY ms_video.youtube_id = BINARY live_streams.youtube_id'));
    }
}
