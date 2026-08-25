<?php

namespace App\Livewire\Public\Videos;

use App\Models\Video;
use Livewire\Component;

class Show extends Component
{
    public Video $video;

    public function mount(Video $video): void
    {
        $this->video = $video->load(['program', 'user']);
    }

    public function render()
    {
        // Related videos: prioritize same program if available, fallback to latest
        if ($this->video->program_id) {
            $relatedVideos = Video::with('program:id,name,slug')
                ->where('program_id', $this->video->program_id)
                ->where('id', '!=', $this->video->id)
                ->orderByDesc('air_date')
                ->orderByDesc('created_at')
                ->take(4)
                ->get();

            // If same program has fewer than 4 videos, supplement with other latest videos
            if ($relatedVideos->count() < 4) {
                $supplement = Video::with('program:id,name,slug')
                    ->where('id', '!=', $this->video->id)
                    ->whereNotIn('id', $relatedVideos->pluck('id'))
                    ->orderByDesc('air_date')
                    ->orderByDesc('created_at')
                    ->take(4 - $relatedVideos->count())
                    ->get();
                $relatedVideos = $relatedVideos->concat($supplement);
            }
        } else {
            $relatedVideos = Video::with('program:id,name,slug')
                ->where('id', '!=', $this->video->id)
                ->orderByDesc('air_date')
                ->orderByDesc('created_at')
                ->take(4)
                ->get();
        }

        // Previous & Next navigation
        $prevVideo = Video::where('id', '!=', $this->video->id)
            ->where(function ($q) {
                $q->where('air_date', '<', $this->video->air_date ?? now())
                    ->orWhere(function ($sub) {
                        $sub->where('air_date', $this->video->air_date)
                            ->where('id', '<', $this->video->id);
                    });
            })
            ->orderByDesc('air_date')
            ->orderByDesc('id')
            ->first(['id', 'title']);

        $nextVideo = Video::where('id', '!=', $this->video->id)
            ->where(function ($q) {
                $q->where('air_date', '>', $this->video->air_date ?? now())
                    ->orWhere(function ($sub) {
                        $sub->where('air_date', $this->video->air_date)
                            ->where('id', '>', $this->video->id);
                    });
            })
            ->orderBy('air_date', 'asc')
            ->orderBy('id', 'asc')
            ->first(['id', 'title']);

        return view('livewire.public.videos.show', [
            'relatedVideos' => $relatedVideos,
            'prevVideo' => $prevVideo,
            'nextVideo' => $nextVideo,
        ])->layout('layouts.public', [
            'title' => "{$this->video->title} | WPiTV — Televisi Digital Kampus",
        ]);
    }
}
