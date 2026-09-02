<?php

namespace Tests\Feature\Public;

use App\Livewire\Public\VisitorCounter;
use App\Models\UserCounter;
use App\Services\VisitorStatisticsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class VisitorCounterTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Carbon::setTestNow(Carbon::parse('2026-09-15 12:00:00', 'Asia/Jakarta'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Cache::flush();
        parent::tearDown();
    }

    public function test_public_metrics_use_existing_hit_semantics_for_each_period(): void
    {
        $this->counter('198.51.100.1', '2026-09-15', 5);
        $this->counter('198.51.100.2', '2026-09-14', 7);
        $this->counter('198.51.100.3', '2026-09-02', 11);
        $this->counter('198.51.100.4', '2026-03-10', 13);
        $this->counter('198.51.100.5', '2025-12-31', 17);

        $this->assertSame([
            'today' => 5,
            'yesterday' => 7,
            'month' => 23,
            'year' => 36,
            'online' => 0,
        ], app(VisitorStatisticsService::class)->publicMetrics());
    }

    public function test_timezone_boundary_uses_asia_jakarta_dates(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-01 00:00:30', 'Asia/Jakarta'));
        $this->counter('198.51.100.10', '2026-09-01', 2);
        $this->counter('198.51.100.11', '2026-08-31', 3);

        $metrics = app(VisitorStatisticsService::class)->publicMetrics();

        $this->assertSame(2, $metrics['today']);
        $this->assertSame(3, $metrics['yesterday']);
        $this->assertSame(2, $metrics['month']);
        $this->assertSame(5, $metrics['year']);
    }

    public function test_empty_data_returns_zero_for_every_metric(): void
    {
        $this->assertSame([
            'today' => 0,
            'yesterday' => 0,
            'month' => 0,
            'year' => 0,
            'online' => 0,
        ], app(VisitorStatisticsService::class)->publicMetrics());
    }

    public function test_online_counts_records_active_during_the_previous_five_minutes(): void
    {
        $this->counter('198.51.100.20', '2026-09-15', 1, now()->subMinutes(4)->timestamp);
        $this->counter('198.51.100.21', '2026-09-15', 1, now()->subMinutes(6)->timestamp);
        $this->counter('198.51.100.22', '2026-09-15', 1, now()->addSecond()->timestamp);

        $this->assertSame(1, app(VisitorStatisticsService::class)->publicMetrics()['online']);
    }

    public function test_public_metrics_are_cached_for_sixty_seconds(): void
    {
        $this->counter('198.51.100.30', '2026-09-15', 2);
        $statistics = app(VisitorStatisticsService::class);

        $this->assertSame(2, $statistics->publicMetrics()['today']);
        $this->counter('198.51.100.31', '2026-09-15', 3);
        $this->assertSame(2, $statistics->publicMetrics()['today']);

        $this->travel(61)->seconds();
        $this->assertSame(5, $statistics->publicMetrics()['today']);
    }

    public function test_component_does_not_write_or_increment_visitor_rows(): void
    {
        $this->counter('198.51.100.40', '2026-09-15', 4);
        $before = UserCounter::query()->get(['id', 'hits', 'online'])->toArray();

        Livewire::test(VisitorCounter::class)->assertSee('Statistik Pengunjung');

        $this->assertSame($before, UserCounter::query()->get(['id', 'hits', 'online'])->toArray());
    }

    public function test_public_html_contains_only_aggregate_values_and_formats_indonesian_numbers(): void
    {
        $secretIp = '203.0.113.199';
        $this->counter($secretIp, '2026-09-15', 1234, now()->timestamp);

        Livewire::test(VisitorCounter::class)
            ->assertSee('visitor-widget', false)
            ->assertSee('visitor-widget__trigger', false)
            ->assertSee('visitor-widget__panel', false)
            ->assertSee('aria-controls="visitorWidgetPanel"', false)
            ->assertSee('aria-expanded="false"', false)
            ->assertSee('Statistik Pengunjung')
            ->assertSee('Hari ini')
            ->assertSee('Kemarin')
            ->assertSee('Bulan ini')
            ->assertSee('Tahun ini')
            ->assertSee('Online')
            ->assertSee('1.234')
            ->assertDontSee('visitor-counter-section')
            ->assertDontSee($secretIp)
            ->assertDontSee('tr_usercounter');
    }

    public function test_homepage_includes_the_public_counter_without_weakening_admin_routes(): void
    {
        $this->get(route('public.home'))
            ->assertOk()
            ->assertSee('visitor-widget', false)
            ->assertSee('Statistik Pengunjung')
            ->assertDontSee('visitor-counter-section');

        $this->get(route('admin.visitor-statistics.index'))->assertRedirect(route('login'));
    }

    public function test_all_major_public_pages_include_floating_visitor_counter(): void
    {
        $routes = [
            'public.home',
            'program.index',
            'video.index',
            'news.index',
            'peliputan.index',
            'agenda.index',
            'tvclub.index',
            'public.about',
        ];

        foreach ($routes as $routeName) {
            $this->get(route($routeName))
                ->assertOk()
                ->assertSee('visitor-widget', false)
                ->assertSee('Statistik Pengunjung')
                ->assertDontSee('visitor-counter-section');
        }
    }

    private function counter(string $ip, string $date, int $hits, int $online = 0): UserCounter
    {
        return UserCounter::create([
            'ip' => $ip,
            'tanggal' => $date,
            'hits' => $hits,
            'online' => $online,
        ]);
    }
}
