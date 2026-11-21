<?php

namespace App\Livewire\Public\LiveStreams;

use App\Models\LiveStream;
use App\Support\LiveStreamErrors;
use App\Support\PublicLiveStreams;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public function render()
    {
        $sections = LiveStreamErrors::run(fn () => $this->sections());

        return view('livewire.public.live-streams.index', compact('sections'))->layout('layouts.public', ['title' => 'Live Streaming — WPiTV']);
    }

    private function sections(): array
    {
        $now = now('Asia/Jakarta')->format('Y-m-d H:i:s');
        $sections = [];
        foreach (['live' => 'Sedang Live', 'upcoming' => 'Jadwal Mendatang', 'pending' => 'Menunggu Pembaruan Operator', 'archive' => 'Arsip Tayangan'] as $key => $title) {
            $query = PublicLiveStreams::query()->leftJoin('wpitv_agendas as schedule', 'schedule.id', '=', 'live_streams.agenda_id');
            if ($key === 'archive') {
                PublicLiveStreams::archive($query);
            } elseif ($key === 'live') {
                $query->where('live_streams.status', 'live');
            } else {
                $query->where('live_streams.status', 'scheduled')->whereRaw(LiveStream::START_WIB.($key === 'upcoming' ? ' > ?' : ' <= ?'), [$now]);
            }
            if ($key === 'archive') {
                $query->orderByRaw('DATE('.LiveStream::START_WIB.') DESC');
            }
            $sections[$key] = ['title' => $title, 'streams' => $query->orderByRaw(LiveStream::START_WIB)->orderBy('live_streams.id')->paginate(6, ['*'], $key.'Page')];
        }

        return $sections;
    }
}
