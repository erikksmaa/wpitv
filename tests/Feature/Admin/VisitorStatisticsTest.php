<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\VisitorStatistics\Index;
use App\Models\Role;
use App\Models\User;
use App\Models\UserCounter;
use App\Services\VisitorTracker;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class VisitorStatisticsTest extends TestCase
{
    use DatabaseTransactions;

    protected User $superAdmin;

    protected User $adminProduction;

    protected User $adminNews;

    protected User $inactiveUser;

    protected function setUp(): void
    {
        parent::setUp();

        $superAdminRole = Role::where('name', 'Super Admin')->firstOrFail();
        $prodRole = Role::where('name', 'Admin Production')->firstOrFail();
        $newsRole = Role::where('name', 'Admin News')->firstOrFail();

        $this->superAdmin = User::factory()->create([
            'name' => 'Super Admin Tester',
            'role_id' => $superAdminRole->id,
            'status' => 1,
        ]);

        $this->adminProduction = User::factory()->create([
            'name' => 'Admin Production Tester',
            'role_id' => $prodRole->id,
            'status' => 1,
        ]);

        $this->adminNews = User::factory()->create([
            'name' => 'Admin News Tester',
            'role_id' => $newsRole->id,
            'status' => 1,
        ]);

        $this->inactiveUser = User::factory()->create([
            'role_id' => $superAdminRole->id,
            'status' => 0,
        ]);
    }

    public function test_unauthenticated_user_cannot_access_visitor_statistics(): void
    {
        $response = $this->get(route('admin.visitor-statistics.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_inactive_user_cannot_access_visitor_statistics(): void
    {
        $response = $this->actingAs($this->inactiveUser)->get(route('admin.visitor-statistics.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_super_admin_can_access_visitor_statistics(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('admin.visitor-statistics.index'));
        $response->assertStatus(200);
        $response->assertSee('Statistik Pengunjung');
        $response->assertSee('Tayangan Harian');
        $response->assertSee('Rincian Harian');
    }

    public function test_admin_production_cannot_access_visitor_statistics(): void
    {
        $response = $this->actingAs($this->adminProduction)->get(route('admin.visitor-statistics.index'));
        $response->assertForbidden();
    }

    public function test_admin_news_cannot_access_visitor_statistics(): void
    {
        $response = $this->actingAs($this->adminNews)->get(route('admin.visitor-statistics.index'));
        $response->assertForbidden();
    }

    public function test_statistics_calculate_total_hits_correctly(): void
    {
        $initialTotalHits = UserCounter::getTotalHits();

        UserCounter::create([
            'ip' => '10.99.88.77',
            'tanggal' => now()->toDateString(),
            'hits' => 15,
            'online' => time(),
        ]);

        $this->assertEquals($initialTotalHits + 15, UserCounter::getTotalHits());
    }

    public function test_statistics_calculate_today_hits_correctly(): void
    {
        $initialTodayHits = UserCounter::getTodayHits();

        UserCounter::create([
            'ip' => '10.99.88.78',
            'tanggal' => now()->toDateString(),
            'hits' => 20,
            'online' => time(),
        ]);

        $this->assertEquals($initialTodayHits + 20, UserCounter::getTodayHits());
    }

    public function test_statistics_calculate_today_visitors_correctly(): void
    {
        $initialTodayVisitors = UserCounter::getTodayVisitors();

        UserCounter::create([
            'ip' => '10.99.88.79',
            'tanggal' => now()->toDateString(),
            'hits' => 1,
            'online' => time(),
        ]);

        $this->assertEquals($initialTodayVisitors + 1, UserCounter::getTodayVisitors());
    }

    public function test_online_visitor_calculation_works(): void
    {
        UserCounter::create([
            'ip' => '10.99.88.80',
            'tanggal' => now()->toDateString(),
            'hits' => 1,
            'online' => time(),
        ]);

        $onlineCount = UserCounter::getOnlineVisitors(5);
        $this->assertGreaterThanOrEqual(1, $onlineCount);
    }

    public function test_date_filtering_works(): void
    {
        $testDate = '2026-08-05';
        UserCounter::create(['ip' => '192.0.2.5', 'tanggal' => $testDate, 'hits' => 2, 'online' => time()]);

        Livewire::actingAs($this->superAdmin)
            ->test(Index::class)
            ->set('period', 'custom')
            ->set('startDate', '2026-08-01')
            ->set('endDate', '2026-08-07')
            ->assertSee('05 Agt 2026');
    }

    public function test_daily_traffic_aggregation_works(): void
    {
        $uniqueIp1 = '192.0.2.10';
        $uniqueIp2 = '192.0.2.20';
        $specificDate = '2026-07-20';

        UserCounter::create([
            'ip' => $uniqueIp1,
            'tanggal' => $specificDate,
            'hits' => 12,
            'online' => time(),
        ]);

        UserCounter::create([
            'ip' => $uniqueIp2,
            'tanggal' => $specificDate,
            'hits' => 18,
            'online' => time(),
        ]);

        Livewire::actingAs($this->superAdmin)
            ->test(Index::class)
            ->set('period', 'custom')
            ->set('startDate', '2026-07-19')
            ->set('endDate', '2026-07-21')
            ->assertSee('20 Jul 2026')
            ->assertSee('30'); // 12 + 18 hits
    }

    public function test_page_does_not_expose_visitor_ip_addresses(): void
    {
        $secretIp = '198.51.100.99';
        UserCounter::create([
            'ip' => $secretIp,
            'tanggal' => now()->toDateString(),
            'hits' => 5,
            'online' => time(),
        ]);

        Livewire::actingAs($this->superAdmin)
            ->test(Index::class)
            ->assertDontSee($secretIp);
    }

    public function test_page_handles_empty_date_range(): void
    {
        Livewire::actingAs($this->superAdmin)
            ->test(Index::class)
            ->set('period', 'custom')
            ->set('startDate', '1990-01-01')
            ->set('endDate', '1990-01-02')
            ->assertSee('Belum ada data pengunjung untuk periode ini.');
    }

    public function test_existing_visitor_tracker_continues_to_work(): void
    {
        $trackerIp = '203.0.113.55';
        $counter = VisitorTracker::track($trackerIp);

        $this->assertNotNull($counter);
        $this->assertDatabaseHas('tr_usercounter', [
            'ip' => $trackerIp,
            'tanggal' => now()->toDateString(),
        ]);
    }

    public function test_chart_library_is_available_on_visitor_statistics(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('admin.visitor-statistics.index'))
            ->assertSee('chart.umd.js', false);
    }
}
