<?php

namespace Tests\Feature\Admin;

use App\Models\EquipmentLoan;
use App\Models\EquipmentLoanItem;
use App\Models\InventoryCategory;
use App\Models\InventoryUnit;
use App\Models\LiveStream;
use App\Models\MonthlyActivityReport;
use App\Models\User;
use App\Services\EquipmentLoanCheckout;
use App\Services\EquipmentReports;
use Database\Seeders\WpitvUatSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class WpitvUatSeederTest extends TestCase
{
    public function test_uat_package_runs_twice_and_reports_use_real_fixtures(): void
    {
        $baseline = $this->snapshot();
        $previousPassword = getenv('WPITV_UAT_PASSWORD');
        putenv('WPITV_UAT_PASSWORD='.bin2hex(random_bytes(24)));
        try {
            $this->seed(WpitvUatSeeder::class);
            $afterFirst = $this->snapshot();
            $this->seed(WpitvUatSeeder::class);
            $this->assertSame($afterFirst, $this->snapshot());
        } finally {
            putenv($previousPassword === false ? 'WPITV_UAT_PASSWORD' : 'WPITV_UAT_PASSWORD='.$previousPassword);
        }
        foreach ($baseline as $table => $rows) {
            $this->assertSame($rows, array_intersect_key($afterFirst[$table], $rows), $table);
        }

        $actors = User::whereIn('username', array_keys(WpitvUatSeeder::ACCOUNTS))->with('role')->get();
        $this->assertEquals(WpitvUatSeeder::ACCOUNTS, $actors->mapWithKeys(fn ($actor) => [$actor->username => $actor->role->name])->all());
        $actor = $actors->firstWhere('username', 'uat_wpitv_v1_production');
        $streams = LiveStream::where('created_by', $actor->id)->with('agenda')->get();
        $this->assertCount(8, $streams);
        $this->assertCount(3, $streams->whereNull('agenda_id'));
        $this->assertCount(4, $streams->where('status', 'ended'));
        $this->assertCount(1, $streams->where('status', 'cancelled'));
        $this->assertCount(1, $streams->filter(fn ($stream) => $stream->agenda?->status === 'draft'));
        $this->assertSame(0, LiveStream::publicVisible()->where('created_by', $actor->id)->count());

        $reports = MonthlyActivityReport::whereIn('created_by', $actors->modelKeys())->with('items')->get();
        $this->assertCount(6, $reports);
        $this->assertCount(3, $reports->pluck('period_start')->unique());
        $this->assertEqualsCanonicalizing(['approved', 'draft', 'revision', 'submitted'], $reports->pluck('status')->unique()->all());
        $this->assertCount(3, $reports->where('division', 'production')->flatMap->items->where('source_type', 'agenda'));

        $loans = EquipmentLoan::where('recorded_by', $actor->id)->pluck('id');
        $this->assertCount(12, $loans);
        $items = EquipmentLoanItem::whereIn('loan_id', $loans)->get();
        $this->assertCount(14, $items);
        $this->assertCount(8, $items->whereNotNull('returned_at'));
        $this->assertCount(1, $items->whereNotNull('lost_at'));
        $this->assertCount(6, $items->whereNull('returned_at')->pluck('inventory_unit_id')->unique());
        $categories = InventoryCategory::where('name', 'like', WpitvUatSeeder::MARKER.' %')->withCount('units')->get();
        $this->assertCount(6, $categories);
        $this->assertEqualsCanonicalizing(array_fill(0, 6, 4), $categories->pluck('units_count')->all());

        $camera = $categories->firstWhere('name', WpitvUatSeeder::MARKER.' Kamera');
        $service = app(EquipmentReports::class);
        $report = $service->page($actor, ['period' => 'custom', 'categoryId' => (string) $camera->id, 'status' => 'returned', 'startDate' => now('Asia/Jakarta')->subMonths(4)->toDateString(), 'endDate' => today('Asia/Jakarta')->toDateString()], 10);
        $this->assertSame(4, $report['summary']['loans']);
        $this->assertSame(4, $report['summary']['returns']);
        $this->assertSame(1, $report['summary']['late']);
        $this->assertSame(4, $report['stock']['available']);
        $this->assertSame(0, $service->page($actor, ['categoryId' => (string) $camera->id, 'status' => 'overdue'], 10)['summary']['loans']);
        $lighting = $categories->firstWhere('name', WpitvUatSeeder::MARKER.' Pencahayaan');
        $overdue = $service->page($actor, [
            'period' => 'custom', 'categoryId' => (string) $lighting->id, 'status' => 'overdue',
            'startDate' => now('Asia/Jakarta')->subDays(7)->toDateString(), 'endDate' => today('Asia/Jakarta')->toDateString(),
        ], 10);
        $this->assertSame(1, $overdue['summary']['loans']);
        $this->assertSame(1, $overdue['summary']['late']);
        $this->assertSame(2, $overdue['stock']['borrowed']);

        $occupied = $items->whereNull('returned_at')->first();
        $unit = InventoryUnit::findOrFail($occupied->inventory_unit_id);
        $date = now('Asia/Jakarta')->subHour()->format('Y-m-d\TH:i');
        try {
            app(EquipmentLoanCheckout::class)->checkoutInTransaction($actor, (string) Str::uuid(), [
                'borrower_name' => 'UAT synthetic', 'student_number' => 'UAT-SECOND', 'study_program' => 'UAT study',
                'whatsapp_number' => '620000000001', 'purpose' => 'UAT duplicate check',
                'borrowed_at' => $date, 'due_at' => now('Asia/Jakarta')->addDay()->format('Y-m-d\TH:i'),
                'items' => [['unit_id' => $unit->id, 'version' => $unit->version]],
            ]);
            $this->fail('An occupied unit must not be checked out twice.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('items', $error->errors());
        }
        $this->assertSame($afterFirst, $this->snapshot());
    }

    public function test_failure_at_report_stage_rolls_back_the_entire_package(): void
    {
        $before = $this->snapshot();
        $dispatcher = MonthlyActivityReport::getEventDispatcher();
        MonthlyActivityReport::setEventDispatcher(clone $dispatcher);
        MonthlyActivityReport::creating(fn () => false);
        $previousPassword = getenv('WPITV_UAT_PASSWORD');
        putenv('WPITV_UAT_PASSWORD='.bin2hex(random_bytes(24)));
        try {
            app(WpitvUatSeeder::class)->run();
            $this->fail('The fixture package must fail when the first report cannot be saved.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('MonthlyActivityException', $error->getMessage());
        } finally {
            MonthlyActivityReport::setEventDispatcher($dispatcher);
            putenv($previousPassword === false ? 'WPITV_UAT_PASSWORD' : 'WPITV_UAT_PASSWORD='.$previousPassword);
        }
        $this->assertSame($before, $this->snapshot());
    }

    private function snapshot(): array
    {
        $tables = [
            'users', 'roles', 'ms_program', 'wpitv_agendas', 'live_streams', 'live_stream_events',
            'inventory_categories', 'inventory_units', 'equipment_loans', 'equipment_loan_items', 'equipment_audit_events',
            'monthly_activity_reports', 'monthly_activity_items', 'monthly_activity_versions', 'monthly_activity_events',
        ];

        return array_combine($tables, array_map(
            fn ($table) => DB::table($table)->orderBy('id')->get()->mapWithKeys(fn ($row) => [$row->id => hash('sha256', serialize($row))])->all(),
            $tables,
        ));
    }
}
