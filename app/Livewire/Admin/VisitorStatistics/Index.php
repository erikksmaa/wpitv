<?php

namespace App\Livewire\Admin\VisitorStatistics;

use App\Models\UserCounter;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('Statistik Pengunjung - Admin WPiTV')]
class Index extends Component
{
    use WithPagination;

    protected string $paginationTheme = 'bootstrap';

    #[Url(history: true)]
    public string $period = '7_days';

    #[Url(history: true)]
    public string $startDate = '';

    #[Url(history: true)]
    public string $endDate = '';

    public int $perPage = 10;

    public function mount(): void
    {
        if (empty($this->startDate)) {
            $this->startDate = now()->subDays(6)->toDateString();
        }
        if (empty($this->endDate)) {
            $this->endDate = now()->toDateString();
        }
    }

    public function updatingPeriod(): void
    {
        $this->resetPage();
    }

    public function updatingStartDate(): void
    {
        $this->resetPage();
    }

    public function updatingEndDate(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function refreshStats(): void
    {
        // Re-render
    }

    protected function getDateRange(): array
    {
        $today = now()->toDateString();

        return match ($this->period) {
            'today' => [$today, $today],
            '7_days' => [now()->subDays(6)->toDateString(), $today],
            '14_days' => [now()->subDays(13)->toDateString(), $today],
            '30_days' => [now()->subDays(29)->toDateString(), $today],
            'this_month' => [now()->startOfMonth()->toDateString(), $today],
            'custom' => [
                ! empty($this->startDate) ? $this->startDate : now()->subDays(6)->toDateString(),
                ! empty($this->endDate) ? $this->endDate : $today,
            ],
            default => [now()->subDays(6)->toDateString(), $today],
        };
    }

    public function render()
    {
        [$start, $end] = $this->getDateRange();

        // 1. Overview Global Cards
        $overviewStats = [
            'total_hits' => UserCounter::getTotalHits(),
            'total_visitors' => UserCounter::getTotalVisitors(),
            'today_hits' => UserCounter::getTodayHits(),
            'today_visitors' => UserCounter::getTodayVisitors(),
            'online_visitors' => UserCounter::getOnlineVisitors(5),
        ];

        // 2. Filtered Period Query (Database Aggregation)
        $periodQuery = UserCounter::query()
            ->whereBetween('tanggal', [$start, $end])
            ->selectRaw('tanggal, COUNT(*) as visitors, SUM(hits) as total_hits')
            ->groupBy('tanggal');

        // 3. Paginated Daily Traffic Table (DESC)
        $dailyTraffic = (clone $periodQuery)
            ->orderBy('tanggal', 'desc')
            ->paginate($this->perPage);

        // 4. Chronological Chart Data (ASC)
        $chartRecords = (clone $periodQuery)
            ->orderBy('tanggal', 'asc')
            ->get();

        $chartLabels = [];
        $chartHits = [];
        $chartVisitors = [];

        foreach ($chartRecords as $record) {
            $date = Carbon::parse($record->tanggal);
            $chartLabels[] = $date->locale('id')->translatedFormat('d M Y');
            $chartHits[] = (int) $record->total_hits;
            $chartVisitors[] = (int) $record->visitors;
        }

        // 5. Period Summary Calculations
        $distinctDays = $chartRecords->count();
        $periodTotalHits = (int) $chartRecords->sum('total_hits');
        $periodTotalVisitors = (int) $chartRecords->sum('visitors');

        $avgDailyHits = $distinctDays > 0 ? (int) round($periodTotalHits / $distinctDays) : 0;
        $avgDailyVisitors = $distinctDays > 0 ? (int) round($periodTotalVisitors / $distinctDays) : 0;

        $highestDay = $chartRecords->sortByDesc('total_hits')->first();
        $lowestDay = $chartRecords->sortBy('total_hits')->first();

        $summary = [
            'start_date' => Carbon::parse($start)->locale('id')->translatedFormat('d M Y'),
            'end_date' => Carbon::parse($end)->locale('id')->translatedFormat('d M Y'),
            'total_days' => $distinctDays,
            'total_hits' => $periodTotalHits,
            'total_visitors' => $periodTotalVisitors,
            'avg_daily_hits' => $avgDailyHits,
            'avg_daily_visitors' => $avgDailyVisitors,
            'highest_day' => $highestDay ? [
                'date' => Carbon::parse($highestDay->tanggal)->locale('id')->translatedFormat('d M Y'),
                'hits' => (int) $highestDay->total_hits,
                'visitors' => (int) $highestDay->visitors,
            ] : null,
            'lowest_day' => $lowestDay ? [
                'date' => Carbon::parse($lowestDay->tanggal)->locale('id')->translatedFormat('d M Y'),
                'hits' => (int) $lowestDay->total_hits,
                'visitors' => (int) $lowestDay->visitors,
            ] : null,
        ];

        return view('livewire.admin.visitor-statistics.index', [
            'overviewStats' => $overviewStats,
            'dailyTraffic' => $dailyTraffic,
            'chartLabels' => $chartLabels,
            'chartHits' => $chartHits,
            'chartVisitors' => $chartVisitors,
            'summary' => $summary,
            'startDateFormatted' => $start,
            'endDateFormatted' => $end,
        ]);
    }
}
