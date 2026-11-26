<?php

namespace App\Livewire\Public\LiveStreams;

use App\Models\LiveStream;
use App\Support\LiveStreamErrors;
use App\Support\PublicLiveStreams;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $tab = '';

    public function render()
    {
        $sections = LiveStreamErrors::run(fn () => $this->sections());
        $activeTab = in_array($this->tab, ['live', 'upcoming', 'archive'], true)
            ? $this->tab
            : ($sections['live']['streams']->total() > 0
                ? 'live'
                : ($sections['upcoming']['streams']->total() > 0 ? 'upcoming' : 'archive'));

        return view('livewire.public.live-streams.index', compact('sections', 'activeTab'))
            ->layout('layouts.public', [
                'title' => 'Live Streaming | WPiTV — Televisi Digital Kampus',
                'description' => 'Saksikan siaran langsung, jadwal mendatang, dan arsip tayangan Live Streaming WPiTV Institut Widya Pratama.',
            ]);
    }

    private function sections(): array
    {
        $now = now('Asia/Jakarta')->format('Y-m-d H:i:s');
        $sections = [];

        $liveQuery = PublicLiveStreams::query()
            ->leftJoin('wpitv_agendas as schedule', 'schedule.id', '=', 'live_streams.agenda_id')
            ->where('live_streams.status', 'live')
            ->orderByRaw(LiveStream::START_WIB)
            ->orderBy('live_streams.id');

        $upcomingQuery = PublicLiveStreams::query()
            ->leftJoin('wpitv_agendas as schedule', 'schedule.id', '=', 'live_streams.agenda_id')
            ->where('live_streams.status', 'scheduled')
            ->orderByRaw('CASE WHEN '.LiveStream::START_WIB.' > ? THEN 0 ELSE 1 END', [$now])
            ->orderByRaw(LiveStream::START_WIB)
            ->orderBy('live_streams.id');

        $archiveQuery = PublicLiveStreams::archive(
            PublicLiveStreams::query()->leftJoin('wpitv_agendas as schedule', 'schedule.id', '=', 'live_streams.agenda_id')
        )
            ->orderByRaw('DATE('.LiveStream::START_WIB.') DESC')
            ->orderByRaw(LiveStream::START_WIB)
            ->orderBy('live_streams.id');

        $sections['live'] = ['title' => 'Sedang Live', 'streams' => $liveQuery->paginate(6, ['*'], 'livePage')];
        $sections['upcoming'] = ['title' => 'Jadwal Mendatang', 'streams' => $upcomingQuery->paginate(6, ['*'], 'upcomingPage')];
        $sections['archive'] = ['title' => 'Arsip Tayangan', 'streams' => $archiveQuery->paginate(6, ['*'], 'archivePage')];

        return $sections;
    }
}
