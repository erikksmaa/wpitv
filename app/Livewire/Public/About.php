<?php

namespace App\Livewire\Public;

use App\Models\News;
use App\Models\Program;
use App\Models\TVClubMember;
use App\Models\Video;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

class About extends Component
{
    public function render()
    {
        $data = app()->environment('testing')
            ? $this->aboutData()
            : Cache::remember('public.about.v1', now()->addSeconds(60), fn () => $this->aboutData());

        return view('livewire.public.about', $data)->layout('layouts.public', [
            'title' => 'Tentang WPiTV | WPiTV — Televisi Digital Kampus',
            'description' => 'Kenali WPiTV, media televisi digital Institut Widya Pratama yang menghadirkan program, tayangan, berita, peliputan, dan ruang kreativitas mahasiswa.',
        ]);
    }

    private function aboutData(): array
    {
        return [
            'heroVideo' => Video::query()
                ->with('program:id,name')
                ->latest('air_date')
                ->first(['id', 'program_id', 'title', 'youtube_id', 'air_date']),
            'stats' => [
                'programs' => Program::active()->count(),
                'videos' => Video::count(),
                'news' => News::count(),
                'members' => TVClubMember::where('status', true)->count(),
            ],
            'crewMembers' => TVClubMember::query()
                ->with('generation:id,name,year')
                ->where('status', true)
                ->latest('id')
                ->limit(6)
                ->get(['id', 'generation_id', 'name', 'photo', 'status']),
        ];
    }
}
