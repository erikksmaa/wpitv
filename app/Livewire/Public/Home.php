<?php

namespace App\Livewire\Public;

use App\Models\News;
use App\Models\Program;
use App\Models\TVClubGeneration;
use App\Models\TVClubGroup;
use App\Models\TVClubMember;
use App\Models\Video;
use App\Models\WpitvAgenda;
use App\Services\VisitorTracker;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Home extends Component
{
    public function mount(): void
    {
        VisitorTracker::track();
    }

    public function render()
    {
        $data = app()->environment('testing')
            ? $this->homepageData()
            : Cache::remember('public.homepage.v5', now()->addSeconds(60), fn () => $this->homepageData());

        return view('livewire.public.home', $data)->layout('layouts.public', [
            'title' => 'WPiTV — Televisi Digital Kampus Institut Widya Pratama',
        ]);
    }

    /**
     * Build the database-backed homepage payload. The result is cached briefly
     * in normal environments to avoid repeating aggregate queries per visit.
     */
    private function homepageData(): array
    {
        // 1. Hero Video (Latest video with program relationship)
        $heroVideo = Video::with('program')->latest('air_date')->first();
        $featuredVideos = $this->featuredVideos($heroVideo);

        // 2. Ticker Items (Mix of latest news & videos)
        $tickerNews = News::latest('created_at')->take(4)->get(['id', 'title', 'created_at']);
        $tickerVideos = Video::latest('air_date')->take(3)->get(['id', 'title', 'air_date']);
        $tickerItems = collect();

        foreach ($tickerNews as $n) {
            $tickerItems->push($n->title);
        }
        foreach ($tickerVideos as $v) {
            $tickerItems->push($v->title);
        }

        if ($tickerItems->isEmpty()) {
            $tickerItems->push('Selamat datang di portal televisi digital WPiTV Institut Widya Pratama');
            $tickerItems->push('WPiTV membuka pengajuan peliputan kegiatan kampus');
        }

        // 4. Programs (Active programs with videos count)
        $programs = Program::where('status', true)
            ->withCount('videos')
            ->orderByDesc('videos_count')
            ->take(8)
            ->get();

        // 5. Latest Videos
        $latestVideos = Video::with('program')
            ->latest('air_date')
            ->take(6)
            ->get();

        // 6. WPiTV in Numbers
        $activeMembers = TVClubMember::where('status', true)->count();
        $stats = [
            'programs' => Program::where('status', true)->count(),
            'videos' => Video::count(),
            'news' => News::count(),
            'members' => $activeMembers,
        ];

        // 7. Latest News (1 featured + 4 small)
        $newsList = News::with('user')
            ->latest('created_at')
            ->take(5)
            ->get();
        $featuredNews = $newsList->first();
        $sideNews = $newsList->slice(1);

        // 8. TV Club Stats & Crew Preview
        $tvclubStats = [
            'generations' => TVClubGeneration::count(),
            'members' => $activeMembers,
            'groups' => TVClubGroup::count(),
            'assignments' => DB::table('tvclub_group_members')->count(),
        ];

        $crewMembers = TVClubMember::with('groups')
            ->where('status', true)
            ->latest('id')
            ->take(6)
            ->get();

        $upcomingAgendas = WpitvAgenda::publicVisible()->upcoming()
            ->with(['program' => fn ($query) => $query->where('status', true)->select('id', 'name', 'slug', 'status')])
            ->orderBy('agenda_date')->orderByRaw('start_time IS NULL')->orderBy('start_time')->orderBy('id')->take(3)->get();

        return [
            'heroVideo' => $heroVideo,
            'featuredVideos' => $featuredVideos,
            'defaultFeaturedVideo' => $featuredVideos[0] ?? null,
            'tickerItems' => $tickerItems,
            'programs' => $programs,
            'latestVideos' => $latestVideos,
            'stats' => $stats,
            'featuredNews' => $featuredNews,
            'sideNews' => $sideNews,
            'tvclubStats' => $tvclubStats,
            'crewMembers' => $crewMembers,
            'upcomingAgendas' => $upcomingAgendas,
        ];
    }

    /**
     * Normalize the latest database video and institutional videos into one
     * client-side selector payload without creating additional video records.
     */
    private function featuredVideos(?Video $heroVideo): array
    {
        $videos = [];

        if ($heroVideo && $this->hasUsableYouTubeId($heroVideo->youtube_id)) {
            $videos[] = [
                'type' => 'database',
                'key' => 'latest',
                'label' => 'Tayangan Terbaru',
                'title' => $heroVideo->title,
                'subtitle' => $heroVideo->program?->name
                    ? 'Program: '.$heroVideo->program->name
                    : 'Tayangan terbaru WPiTV',
                'context' => $heroVideo->program?->name ?? 'Tayangan WPiTV terbaru',
                'youtubeId' => $heroVideo->youtube_id,
                'embedUrl' => $heroVideo->embed_url,
                'thumbnailUrl' => $heroVideo->thumbnail_url,
                'detailUrl' => route('video.show', $heroVideo),
                'externalUrl' => $heroVideo->watch_url,
                'date' => $heroVideo->air_date?->isoFormat('D MMMM Y'),
                'program' => $heroVideo->program?->name,
                'metaPrimary' => $heroVideo->air_date?->isoFormat('D MMMM Y') ?? 'Tayangan terbaru',
                'metaSecondary' => $heroVideo->program?->name,
                'metaType' => 'latest',
            ];
        }

        foreach (config('wpitv.featured_videos', []) as $key => $video) {
            $youtubeId = (string) ($video['youtube_id'] ?? '');

            if (! $this->hasUsableYouTubeId($youtubeId)) {
                continue;
            }

            $title = (string) ($video['title'] ?? 'Video WPiTV');

            $videos[] = [
                'type' => 'static',
                'key' => (string) $key,
                'label' => (string) ($video['label'] ?? $title),
                'title' => $title,
                'subtitle' => (string) ($video['subtitle'] ?? ''),
                'context' => (string) ($video['context'] ?? ''),
                'youtubeId' => $youtubeId,
                'embedUrl' => "https://www.youtube.com/embed/{$youtubeId}",
                'thumbnailUrl' => "https://img.youtube.com/vi/{$youtubeId}/hqdefault.jpg",
                'detailUrl' => null,
                'externalUrl' => "https://www.youtube.com/watch?v={$youtubeId}",
                'date' => null,
                'program' => null,
                'metaPrimary' => $title,
                'metaSecondary' => $key === 'pmb' ? 'Institut Widya Pratama' : (string) ($video['label'] ?? ''),
                'metaType' => 'institutional',
            ];
        }

        return $videos;
    }

    private function hasUsableYouTubeId(?string $youtubeId): bool
    {
        return is_string($youtubeId)
            && preg_match('/^[A-Za-z0-9_-]{6,}$/', $youtubeId) === 1;
    }
}
