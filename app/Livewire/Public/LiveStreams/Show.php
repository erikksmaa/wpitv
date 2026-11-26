<?php

namespace App\Livewire\Public\LiveStreams;

use App\Support\LiveStreamErrors;
use App\Support\PublicLiveStreams;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Show extends Component
{
    #[Locked]
    public int $liveStream;

    public function mount(int $liveStream): void
    {
        $this->liveStream = $liveStream;
    }

    public function render()
    {
        // Recheck visibility on every Livewire request; no public model snapshot.
        $stream = LiveStreamErrors::run(fn () => PublicLiveStreams::query()->findOrFail($this->liveStream));
        $relatedStreams = LiveStreamErrors::run(fn () => PublicLiveStreams::query()
            ->leftJoin('wpitv_agendas as schedule', 'schedule.id', '=', 'live_streams.agenda_id')
            ->where('live_streams.id', '!=', $stream->id)
            ->whereIn('live_streams.status', ['live', 'scheduled'])
            ->orderByRaw("CASE WHEN live_streams.status = 'live' THEN 0 ELSE 1 END")
            ->orderByRaw(\App\Models\LiveStream::START_WIB)
            ->limit(3)
            ->get());

        $description = trim((string) $stream->display_description) !== ''
            ? \Illuminate\Support\Str::limit(strip_tags((string) $stream->display_description), 160)
            : 'Saksikan siaran langsung dan informasi jadwal '.$stream->display_title.' di WPiTV Institut Widya Pratama.';

        return view('livewire.public.live-streams.show', compact('stream', 'relatedStreams'))->layout('layouts.public', [
            'title' => $stream->display_title.' | Live Streaming — WPiTV',
            'description' => $description,
        ]);
    }
}
