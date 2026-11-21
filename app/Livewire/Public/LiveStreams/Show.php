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

        return view('livewire.public.live-streams.show', compact('stream'))->layout('layouts.public', ['title' => $stream->display_title.' — Live WPiTV']);
    }
}
