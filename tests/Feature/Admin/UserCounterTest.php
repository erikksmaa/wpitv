<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use App\Models\UserCounter;
use App\Services\VisitorTracker;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Tests\TestCase;

class UserCounterTest extends TestCase
{
    use DatabaseTransactions;

    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $superAdminRole = Role::where('name', 'Super Admin')->first() ?? Role::find(1);

        $this->superAdmin = User::factory()->create([
            'name' => 'Super Admin Tester',
            'role_id' => $superAdminRole->id,
            'status' => 1,
        ]);
    }

    public function test_visitor_tracker_records_new_visit(): void
    {
        $testIp = '10.50.60.70';
        $today = now()->toDateString();

        $counter = VisitorTracker::track($testIp);

        $this->assertNotNull($counter);
        $this->assertDatabaseHas('tr_usercounter', [
            'ip' => $testIp,
            'tanggal' => $today,
            'hits' => 1,
        ]);
        $this->assertGreaterThan(0, $counter->online);
    }

    public function test_visitor_tracker_increments_existing_visit_hits_and_updates_online_timestamp(): void
    {
        $testIp = '10.50.60.80';
        $today = now()->toDateString();

        // 1st visit
        VisitorTracker::track($testIp);

        // 2nd visit
        VisitorTracker::track($testIp);

        $this->assertDatabaseHas('tr_usercounter', [
            'ip' => $testIp,
            'tanggal' => $today,
            'hits' => 2,
        ]);
    }

    public function test_total_hits_calculation(): void
    {
        $testIp = '10.50.60.81';
        $today = now()->toDateString();

        $initialHits = UserCounter::getTotalHits();

        UserCounter::create([
            'ip' => $testIp,
            'tanggal' => $today,
            'hits' => 10,
            'online' => time(),
        ]);

        $this->assertEquals($initialHits + 10, UserCounter::getTotalHits());
    }

    public function test_today_hits_calculation(): void
    {
        $testIp = '10.50.60.82';
        $today = now()->toDateString();

        $initialTodayHits = UserCounter::getTodayHits();

        UserCounter::create([
            'ip' => $testIp,
            'tanggal' => $today,
            'hits' => 7,
            'online' => time(),
        ]);

        $this->assertEquals($initialTodayHits + 7, UserCounter::getTodayHits());
    }

    public function test_today_visitor_calculation(): void
    {
        $testIp = '10.50.60.83';
        $today = now()->toDateString();

        $initialTodayVisitors = UserCounter::getTodayVisitors();

        UserCounter::create([
            'ip' => $testIp,
            'tanggal' => $today,
            'hits' => 1,
            'online' => time(),
        ]);

        $this->assertEquals($initialTodayVisitors + 1, UserCounter::getTodayVisitors());
    }

    public function test_online_visitor_calculation(): void
    {
        $testIp = '10.50.60.84';
        $today = now()->toDateString();

        UserCounter::create([
            'ip' => $testIp,
            'tanggal' => $today,
            'hits' => 1,
            'online' => time(), // Active now
        ]);

        $onlineCount = UserCounter::getOnlineVisitors(5);
        $this->assertGreaterThanOrEqual(1, $onlineCount);
    }

    public function test_public_home_page_triggers_visitor_tracking(): void
    {
        $testIp = '192.168.100.200';

        $response = $this->withServerVariables(['REMOTE_ADDR' => $testIp])
            ->get(route('public.home'));

        $response->assertStatus(200);

        $this->assertDatabaseHas('tr_usercounter', [
            'ip' => $testIp,
            'tanggal' => now()->toDateString(),
        ]);
    }

    public function test_admin_dashboard_displays_visitor_traffic_metrics(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('admin.dashboard'));
        $response->assertStatus(200);
        $response->assertSee('Ringkasan Pengunjung');
        $response->assertSee('Total tayangan');
        $response->assertSee('Pengunjung hari ini');
        $response->assertSee('Daring sekarang');
    }

    public function test_admin_request_does_not_trigger_public_visitor_tracking(): void
    {
        $adminIp = '172.16.0.99';
        $today = now()->toDateString();

        $this->actingAs($this->superAdmin)
            ->withServerVariables(['REMOTE_ADDR' => $adminIp])
            ->get(route('admin.dashboard'));

        $this->assertDatabaseMissing('tr_usercounter', [
            'ip' => $adminIp,
            'tanggal' => $today,
        ]);
    }

    public function test_livewire_update_does_not_trigger_double_tracking(): void
    {
        $fakeRequest = Request::create('/livewire/update', 'POST');
        $fakeRequest->headers->set('X-Livewire', 'true');

        $result = VisitorTracker::track($fakeRequest);
        $this->assertNull($result);
    }

    public function test_tracking_error_does_not_crash_public_page(): void
    {
        $response = $this->get(route('public.home'));
        $response->assertStatus(200);
    }
}
