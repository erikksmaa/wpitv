<?php

namespace App\Services;

use App\Models\Coverage;
use App\Models\News;
use App\Models\Program;
use App\Models\Role;
use App\Models\TVClubGeneration;
use App\Models\TVClubGroup;
use App\Models\TVClubGroupMember;
use App\Models\TVClubMember;
use App\Models\TvClubOrganizationPosition;
use App\Models\User;
use App\Models\UserCounter;
use App\Models\Video;
use App\Models\WpitvAgenda;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AdminDashboardMetrics
{
    public function get(User $user): array
    {
        Gate::forUser($user)->authorize('access-admin');

        // Select query groups before querying; hidden cards must not load private data.
        $data = match ($user->role->name) {
            User::ROLE_SUPER_ADMIN => $this->systemOverview(),
            User::ROLE_PRODUCTION => $this->productionOverview(),
            User::ROLE_NEWS => $this->editorialOverview(),
            User::ROLE_TVCLUB => $this->clubOverview(),
        };

        if ($user->isSuperAdmin() || $user->isAdminProduction()) {
            $data['agenda'] = [
                'count' => WpitvAgenda::upcoming()->where('status', 'scheduled')->count(),
                'latest' => WpitvAgenda::upcoming()->where('status', 'scheduled')
                    ->orderBy('agenda_date')->orderBy('start_time')->limit(5)
                    ->get(['id', 'title', 'agenda_date', 'start_time', 'end_time', 'type', 'visibility']),
            ];

        }

        $live = app(LiveDashboardMetrics::class)->get($user);
        if ($live !== null) {
            $data['live'] = $live;
        }

        return $data;
    }

    private function systemOverview(): array
    {
        $data = $this->fullOverview();
        $data['system'] = [
            'active_users' => User::where('status', true)->count(),
            'inactive_users' => User::where('status', false)->count(),
            'roles' => Role::select(['id', 'name'])->withCount('users')->orderBy('name')->get(),
        ];

        return $data;
    }

    private function productionOverview(): array
    {
        return [
            'kpis' => [
                'programs' => $this->programKpi(),
                'videos' => $this->videoKpi(),
                'groups' => TVClubGroup::count(),
            ],
            'coverage' => $this->coverageOverview(),
            'programs' => $this->recentPrograms(),
            'latest' => ['videos' => $this->recentVideos()],
        ];
    }

    private function editorialOverview(): array
    {
        return [
            'kpis' => [
                'news' => [
                    'total' => News::count(),
                    'this_month' => News::whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->count(),
                    'updated_this_week' => News::whereBetween('updated_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
                ],
                'videos' => $this->videoKpi(),
            ],
            'latest' => [
                'news' => News::orderByDesc('updated_at')->orderByDesc('id')->limit(5)
                    ->get(['id', 'title', 'slug', 'author_name', 'created_at', 'updated_at']),
                'videos' => $this->recentVideos(),
            ],
        ];
    }

    private function clubOverview(): array
    {
        $generation = TVClubGeneration::where('status', true)->orderByDesc('year')->first(['id', 'name', 'year']);
        $positions = TvClubOrganizationPosition::query()->where('generation_id', $generation?->id)->where('status', true);
        $positionCount = (clone $positions)->count();
        $filledPositions = (clone $positions)->whereHas('assignments.member', fn ($q) => $q->where('status', true))->count();

        return [
            'kpis' => ['programs' => $this->programKpi()],
            'programs' => $this->recentPrograms(),
            'tv_club' => [
                'generation' => $generation,
                'active_generations' => TVClubGeneration::where('status', true)->count(),
                'members' => TVClubMember::count(),
                'active_members' => TVClubMember::where('status', true)->count(),
                'groups' => TVClubGroup::count(),
                'positions' => TvClubOrganizationPosition::count(),
                'filled_positions' => $filledPositions,
                'vacant_positions' => $positionCount - $filledPositions,
                'recent_members' => TVClubMember::where('status', true)->with('generation:id,name')
                    ->latest('id')->limit(5)->get(['id', 'generation_id', 'name']),
                'top_groups' => TVClubGroup::select(['id', 'generation_id', 'program_id', 'name'])->with(['generation:id,name', 'program:id,name'])
                    ->withCount('members')->orderByDesc('status')->latest('id')->limit(5)
                    ->get(),
            ],
        ];
    }

    private function programKpi(): array
    {
        return ['total' => Program::count(), 'active' => Program::active()->count()];
    }

    private function videoKpi(): array
    {
        return ['total' => Video::count(), 'this_month' => Video::whereBetween('air_date', [now()->startOfMonth(), now()->endOfMonth()])->count()];
    }

    private function recentPrograms(): array
    {
        return ['recent' => Program::select(['id', 'name', 'slug', 'status'])->withCount('tvClubGroups')->latest('updated_at')->limit(5)->get()];
    }

    private function recentVideos(): Collection
    {
        return Video::with('program:id,name')->orderByDesc('air_date')->latest('id')->limit(5)
            ->get(['id', 'program_id', 'title', 'youtube_id', 'air_date']);
    }

    private function fullOverview(): array
    {
        $programsWithoutVideos = Program::query()
            ->where('status', true)
            ->whereDoesntHave('videos')
            ->count();
        $programKpi = Program::query()
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as active')
            ->first();
        $videoKpi = Video::query()
            ->selectRaw(
                'COUNT(*) as total, SUM(CASE WHEN air_date BETWEEN ? AND ? THEN 1 ELSE 0 END) as this_month',
                [now()->startOfMonth(), now()->endOfMonth()]
            )
            ->first();
        $newsKpi = News::query()
            ->selectRaw(
                'COUNT(*) as total, SUM(CASE WHEN created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as this_month',
                [now()->startOfMonth(), now()->endOfMonth()]
            )
            ->first();
        $coverage = $this->coverageOverview();

        return [
            'kpis' => [
                'programs' => [
                    'total' => (int) $programKpi->total,
                    'active' => (int) $programKpi->active,
                    'without_videos' => $programsWithoutVideos,
                ],
                'videos' => [
                    'total' => (int) $videoKpi->total,
                    'this_month' => (int) $videoKpi->this_month,
                ],
                'news' => [
                    'total' => (int) $newsKpi->total,
                    'this_month' => (int) $newsKpi->this_month,
                ],
                'coverage' => [
                    'pending' => $coverage['counts'][Coverage::STATUS_PENDING],
                    'completed_this_month' => Coverage::where('status', Coverage::STATUS_COMPLETED)
                        ->whereBetween('updated_at', [now()->startOfMonth(), now()->endOfMonth()])
                        ->count(),
                ],
            ],
            'production' => $this->productionTrend(),
            'coverage' => $coverage,
            'traffic' => $this->trafficOverview(),
            'programs' => [
                'ranking' => Program::query()
                    ->withCount('videos')
                    ->orderByDesc('videos_count')
                    ->orderBy('name')
                    ->limit(5)
                    ->get(),
                'without_videos' => $programsWithoutVideos,
            ],
            'health' => [
                'programs_without_videos' => $programsWithoutVideos,
                'videos_without_program' => Video::whereNull('program_id')->count(),
                'programs_without_image' => Program::where(fn ($query) => $query->whereNull('image')->orWhere('image', ''))->count(),
                'news_without_image' => News::where(fn ($query) => $query->whereNull('image')->orWhere('image', ''))->count(),
            ],
            'latest' => [
                'videos' => Video::query()
                    ->with('program:id,name')
                    ->orderByDesc('air_date')
                    ->orderByDesc('id')
                    ->limit(5)
                    ->get(),
                'news' => News::query()
                    ->with('user:id,name')
                    ->latest()
                    ->limit(5)
                    ->get(),
            ],
            'tv_club' => [
                'active_generations' => TVClubGeneration::where('status', true)->count(),
                'active_members' => TVClubMember::where('status', true)->count(),
                'groups' => TVClubGroup::count(),
                'assignments' => TVClubGroupMember::count(),
                'top_groups' => TVClubGroup::query()
                    ->with(['generation:id,name,year', 'program:id,name'])
                    ->withCount('members')
                    ->orderByDesc('members_count')
                    ->orderBy('name')
                    ->limit(5)
                    ->get(),
            ],
        ];
    }

    private function productionTrend(): array
    {
        $months = collect(range(5, 0))->map(
            fn (int $offset) => now()->startOfMonth()->subMonths($offset)
        );
        $start = $months->first()->copy()->startOfMonth();
        $end = now()->endOfMonth();
        $videoExpression = $this->monthExpression('air_date');
        $newsExpression = $this->monthExpression('created_at');

        $videos = Video::query()
            ->whereNotNull('air_date')
            ->whereBetween('air_date', [$start, $end])
            ->selectRaw("{$videoExpression} as month_key, COUNT(*) as total")
            ->groupBy('month_key')
            ->pluck('total', 'month_key');

        $news = News::query()
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw("{$newsExpression} as month_key, COUNT(*) as total")
            ->groupBy('month_key')
            ->pluck('total', 'month_key');

        return [
            'labels' => $months->map(fn (Carbon $month) => $month->locale('id')->translatedFormat('M Y'))->values()->all(),
            'videos' => $this->mapMonthlyValues($months, $videos),
            'news' => $this->mapMonthlyValues($months, $news),
        ];
    }

    private function coverageCounts(): array
    {
        $counts = Coverage::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            Coverage::STATUS_PENDING => (int) ($counts[Coverage::STATUS_PENDING] ?? 0),
            Coverage::STATUS_APPROVED => (int) ($counts[Coverage::STATUS_APPROVED] ?? 0),
            Coverage::STATUS_IN_PROGRESS => (int) ($counts[Coverage::STATUS_IN_PROGRESS] ?? 0),
            Coverage::STATUS_COMPLETED => (int) ($counts[Coverage::STATUS_COMPLETED] ?? 0),
            Coverage::STATUS_REJECTED => (int) ($counts[Coverage::STATUS_REJECTED] ?? 0),
        ];
    }

    private function coverageOverview(): array
    {
        return [
            'counts' => $this->coverageCounts(),
            'latest' => Coverage::query()
                ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [Coverage::STATUS_PENDING])
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(['id', 'event_name', 'event_date', 'status', 'created_at']),
        ];
    }

    private function trafficOverview(): array
    {
        $dates = collect(range(6, 0))->map(fn (int $offset) => today()->subDays($offset));
        $daily = UserCounter::query()
            ->whereBetween('tanggal', [$dates->first()->toDateString(), $dates->last()->toDateString()])
            ->selectRaw('tanggal, COUNT(*) as visitors, SUM(hits) as hits')
            ->groupBy('tanggal')
            ->orderBy('tanggal')
            ->get()
            ->keyBy(fn (UserCounter $row) => Carbon::parse($row->tanggal)->toDateString());

        return [
            'labels' => $dates->map(fn (Carbon $date) => $date->locale('id')->translatedFormat('d M'))->all(),
            'hits' => $dates->map(fn (Carbon $date) => (int) ($daily->get($date->toDateString())?->hits ?? 0))->all(),
            'visitors' => $dates->map(fn (Carbon $date) => (int) ($daily->get($date->toDateString())?->visitors ?? 0))->all(),
            'metrics' => VisitorTracker::getStats(),
        ];
    }

    private function mapMonthlyValues(Collection $months, Collection $values): array
    {
        return $months
            ->map(fn (Carbon $month) => (int) ($values[$month->format('Y-m')] ?? 0))
            ->values()
            ->all();
    }

    private function monthExpression(string $column): string
    {
        return match (DB::getDriverName()) {
            'sqlite' => "strftime('%Y-%m', {$column})",
            'pgsql' => "to_char({$column}, 'YYYY-MM')",
            default => "DATE_FORMAT({$column}, '%Y-%m')",
        };
    }
}
