<?php

namespace Tests\Feature\Admin;

use App\Models\Coverage;
use App\Models\News;
use App\Models\Program;
use App\Models\Role;
use App\Models\TVClubGeneration;
use App\Models\TVClubGroup;
use App\Models\TVClubMember;
use App\Models\User;
use App\Models\UserCounter;
use App\Models\Video;
use App\Services\AdminDashboardMetrics;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use DatabaseTransactions;

    protected User $superAdmin;

    protected User $adminProduction;

    protected User $adminNews;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = $this->makeUser('Super Admin', 'Dashboard Super Admin');
        $this->adminProduction = $this->makeUser('Admin Production', 'Dashboard Production');
        $this->adminNews = $this->makeUser('Admin News', 'Dashboard News');
    }

    public function test_dashboard_loads_for_every_active_admin_role(): void
    {
        foreach ([$this->superAdmin, $this->adminProduction, $this->adminNews] as $user) {
            $this->actingAs($user)
                ->get(route('admin.dashboard'))
                ->assertOk()
                ->assertSee("Selamat datang, {$user->name}")
                ->assertSee('WPiTV Media Operations');
        }
    }

    public function test_program_kpi_is_database_driven_and_counts_active_programs(): void
    {
        $before = app(AdminDashboardMetrics::class)->get()['kpis']['programs'];
        $active = $this->makeProgram('Program Aktif Dashboard', true);
        $this->makeProgram('Program Nonaktif Dashboard', false);

        $after = app(AdminDashboardMetrics::class)->get()['kpis']['programs'];

        $this->assertSame($before['total'] + 2, $after['total']);
        $this->assertSame($before['active'] + 1, $after['active']);
        $this->assertTrue($active->status);
    }

    public function test_video_and_news_kpis_use_actual_records(): void
    {
        $before = app(AdminDashboardMetrics::class)->get()['kpis'];
        Video::create(['title' => 'Tayangan KPI Dashboard', 'youtube_id' => 'dashKpi0001', 'air_date' => today()]);
        News::create(['title' => 'Berita KPI Dashboard', 'slug' => 'berita-kpi-'.Str::uuid(), 'content' => 'Konten pengujian']);
        $after = app(AdminDashboardMetrics::class)->get()['kpis'];

        $this->assertSame($before['videos']['total'] + 1, $after['videos']['total']);
        $this->assertSame($before['videos']['this_month'] + 1, $after['videos']['this_month']);
        $this->assertSame($before['news']['total'] + 1, $after['news']['total']);
        $this->assertSame($before['news']['this_month'] + 1, $after['news']['this_month']);
    }

    public function test_pending_coverage_count_and_latest_priority_are_correct(): void
    {
        $approved = $this->makeCoverage('Peliputan Disetujui Dashboard', Coverage::STATUS_APPROVED);
        $pending = $this->makeCoverage('Peliputan Menunggu Dashboard', Coverage::STATUS_PENDING);
        $dashboard = app(AdminDashboardMetrics::class)->get();

        $this->assertSame(Coverage::where('status', Coverage::STATUS_PENDING)->count(), $dashboard['kpis']['coverage']['pending']);
        $this->assertSame(Coverage::STATUS_PENDING, $dashboard['coverage']['latest']->first()->status);
        $this->assertTrue($dashboard['coverage']['latest']->contains($pending));
        $this->assertNotNull($approved->id);
    }

    public function test_monthly_production_aggregate_includes_current_video_and_news(): void
    {
        $before = app(AdminDashboardMetrics::class)->get()['production'];
        Video::create(['title' => 'Agregat Video Dashboard', 'youtube_id' => 'dashAgg0001', 'air_date' => today()]);
        News::create(['title' => 'Agregat Berita Dashboard', 'slug' => 'agregat-'.Str::uuid(), 'content' => 'Konten agregat']);
        $after = app(AdminDashboardMetrics::class)->get()['production'];

        $this->assertCount(6, $after['labels']);
        $this->assertSame($before['videos'][5] + 1, $after['videos'][5]);
        $this->assertSame($before['news'][5] + 1, $after['news'][5]);
    }

    public function test_traffic_summary_uses_counter_table(): void
    {
        $before = app(AdminDashboardMetrics::class)->get()['traffic'];
        UserCounter::create(['ip' => '198.51.100.44', 'tanggal' => today(), 'hits' => 8, 'online' => time()]);
        $after = app(AdminDashboardMetrics::class)->get()['traffic'];

        $this->assertSame($before['hits'][6] + 8, $after['hits'][6]);
        $this->assertSame($before['visitors'][6] + 1, $after['visitors'][6]);
        $this->assertSame($before['metrics']['total_hits'] + 8, $after['metrics']['total_hits']);
    }

    public function test_latest_content_is_limited_to_five_records(): void
    {
        foreach (range(1, 6) as $index) {
            Video::create(['title' => "Video Terbaru {$index}", 'youtube_id' => "latestDash{$index}", 'air_date' => today()->addDays($index)]);
            $news = News::create(['title' => "Berita Terbaru {$index}", 'slug' => 'latest-news-'.Str::uuid(), 'content' => 'Konten terbaru']);
            $news->forceFill(['created_at' => now()->addMinutes($index)])->save();
        }

        $latest = app(AdminDashboardMetrics::class)->get()['latest'];

        $this->assertCount(5, $latest['videos']);
        $this->assertCount(5, $latest['news']);
        $this->assertSame('Video Terbaru 6', $latest['videos']->first()->title);
        $this->assertSame('Berita Terbaru 6', $latest['news']->first()->title);
    }

    public function test_program_ranking_uses_videos_count(): void
    {
        $program = $this->makeProgram('Program Ranking Dashboard', true);
        foreach (range(1, 3) as $index) {
            Video::create(['program_id' => $program->id, 'title' => "Ranking {$index}", 'youtube_id' => "rankDash{$index}", 'air_date' => today()]);
        }

        $ranked = app(AdminDashboardMetrics::class)->get()['programs']['ranking']->firstWhere('id', $program->id);

        if ($ranked) {
            $this->assertSame(3, $ranked->videos_count);
        } else {
            $this->assertGreaterThanOrEqual(3, app(AdminDashboardMetrics::class)->get()['programs']['ranking']->first()->videos_count);
        }
    }

    public function test_content_health_counts_unlinked_video_and_missing_images(): void
    {
        $before = app(AdminDashboardMetrics::class)->get()['health'];
        Video::create(['title' => 'Video Tanpa Program Dashboard', 'youtube_id' => 'unlinkedDash', 'air_date' => today()]);
        $this->makeProgram('Program Tanpa Gambar Dashboard', true, null);
        News::create(['title' => 'Berita Tanpa Gambar Dashboard', 'slug' => 'no-image-'.Str::uuid(), 'content' => 'Konten', 'image' => null]);
        $after = app(AdminDashboardMetrics::class)->get()['health'];

        $this->assertSame($before['videos_without_program'] + 1, $after['videos_without_program']);
        $this->assertSame($before['programs_without_image'] + 1, $after['programs_without_image']);
        $this->assertSame($before['news_without_image'] + 1, $after['news_without_image']);
    }

    public function test_tv_club_metrics_and_group_members_are_database_driven(): void
    {
        $before = app(AdminDashboardMetrics::class)->get()['tv_club'];
        $generation = TVClubGeneration::create(['name' => 'Generasi Dashboard', 'year' => 2097, 'status' => true]);
        $member = TVClubMember::create(['generation_id' => $generation->id, 'student_number' => 'DASH-001', 'name' => 'Kru Dashboard', 'status' => true]);
        $program = $this->makeProgram('Program TV Club Dashboard', true);
        $group = TVClubGroup::create(['generation_id' => $generation->id, 'program_id' => $program->id, 'name' => 'Kelompok Dashboard', 'status' => true]);
        $group->members()->attach($member->id, ['role' => 'Produser']);
        $after = app(AdminDashboardMetrics::class)->get()['tv_club'];

        $this->assertSame($before['active_generations'] + 1, $after['active_generations']);
        $this->assertSame($before['active_members'] + 1, $after['active_members']);
        $this->assertSame($before['groups'] + 1, $after['groups']);
        $this->assertSame($before['assignments'] + 1, $after['assignments']);
    }

    public function test_quick_actions_follow_each_role_policy(): void
    {
        $this->actingAs($this->superAdmin)->get(route('admin.dashboard'))
            ->assertSee('Tambah Program')->assertSee('Tulis Berita')->assertSee('Tinjau Peliputan');

        $this->actingAs($this->adminProduction)->get(route('admin.dashboard'))
            ->assertSee('Tambah Program')->assertSee('Tambah Banner')->assertDontSee('Tulis Berita');

        $this->actingAs($this->adminNews)->get(route('admin.dashboard'))
            ->assertSee('Tambah Tayangan')->assertSee('Tulis Berita')->assertDontSee('Tambah Program')
            ->assertDontSee('Tambah Banner')->assertDontSee('Kelola TV Club')->assertDontSee('Tinjau Peliputan');
    }

    public function test_dashboard_does_not_render_fake_analytics_or_system_actions(): void
    {
        $this->actingAs($this->adminProduction)->get(route('admin.dashboard'))
            ->assertDontSee('+18%')
            ->assertDontSee('Conversion Rate')
            ->assertDontSee('Bounce Rate')
            ->assertDontSee('Kelola Pengguna')
            ->assertDontSee('Kelola Peran');
    }

    public function test_dashboard_query_count_stays_bounded_without_n_plus_one(): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        app(AdminDashboardMetrics::class)->get();
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(40, $queryCount);
    }

    public function test_chart_library_is_only_loaded_on_chart_pages(): void
    {
        $this->actingAs($this->superAdmin)->get(route('admin.dashboard'))
            ->assertSee('chart.umd.js', false);

        $this->actingAs($this->superAdmin)->get(route('admin.programs.index'))
            ->assertDontSee('chart.umd.js', false);
    }

    private function makeUser(string $roleName, string $name): User
    {
        $role = Role::where('name', $roleName)->firstOrFail();

        return User::factory()->create(['role_id' => $role->id, 'name' => $name, 'status' => true]);
    }

    private function makeProgram(string $name, bool $status, ?string $image = 'dashboard.jpg'): Program
    {
        return Program::create(['name' => $name, 'slug' => Str::slug($name).'-'.Str::uuid(), 'image' => $image, 'status' => $status]);
    }

    private function makeCoverage(string $eventName, string $status): Coverage
    {
        return Coverage::create([
            'submission_code' => 'PLP-DASH-'.Str::upper(Str::random(8)),
            'name' => 'Pemohon Dashboard',
            'email' => 'dashboard@example.test',
            'phone' => '081234567890',
            'event_name' => $eventName,
            'event_date' => today()->addDays(3),
            'event_time' => '09:00',
            'location' => 'Studio WPiTV',
            'description' => 'Pengujian dashboard',
            'status' => $status,
        ]);
    }
}
