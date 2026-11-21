<?php

namespace Tests\Integration\Equipment;

use App\Exceptions\EquipmentOperationException;
use App\Livewire\Admin\EquipmentLoans\CorrectionForm;
use App\Livewire\Admin\EquipmentLoans\Form as LoanForm;
use App\Livewire\Admin\EquipmentLoans\ReturnForm;
use App\Livewire\Admin\Inventory\Categories\Form as CategoryForm;
use App\Livewire\Admin\Inventory\Units\Form as UnitForm;
use App\Models\EquipmentAuditEvent;
use App\Models\EquipmentLoan;
use App\Models\EquipmentLoanItem;
use App\Models\InventoryCategory;
use App\Models\InventoryUnit;
use App\Models\User;
use App\Services\AdminDashboardMetrics;
use App\Services\EquipmentLoanCheckout;
use App\Services\EquipmentLoanCorrection;
use App\Services\EquipmentLoanExtension;
use App\Services\EquipmentLoanLoss;
use App\Services\EquipmentLoanReturn;
use App\Services\EquipmentPhotos;
use App\Services\EquipmentReports;
use App\Services\InventoryUnitRecovery;
use App\Support\EquipmentErrors;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use RuntimeException;
use Tests\Support\EquipmentScenarioTestCase;

class EquipmentIntegrationTest extends EquipmentScenarioTestCase
{
    private function withReportClock(string $wallTime, callable $test): void
    {
        $mutableClock = Carbon::getTestNow();
        $immutableClock = CarbonImmutable::getTestNow();
        $instant = CarbonImmutable::parse($wallTime, 'Asia/Jakarta');
        try {
            Carbon::setTestNow($instant);
            CarbonImmutable::setTestNow($instant);
            $test();
        } finally {
            // Also restores an outer frozen clock when this helper is nested.
            Carbon::setTestNow($mutableClock);
            CarbonImmutable::setTestNow($immutableClock);
        }
    }

    private function rejected(callable $action, ?string $code = null): void
    {
        try {
            $action();
            $this->fail('Operation unexpectedly accepted.');
        } catch (EquipmentOperationException|ValidationException|AuthorizationException $e) {
            if ($code !== null) {
                $this->assertInstanceOf(EquipmentOperationException::class, $e);
                $this->assertSame($code, $e->errorCode);
            }
            $this->assertNull($e->getPrevious());
        }
    }

    public function test_category_livewire_crud_uniqueness_stale_and_archive_are_audited(): void
    {
        $this->actingAs($this->actor());
        $name = $this->prefix.' created';
        $form = Livewire::test(CategoryForm::class)->set('name', $name)->set('description', 'Synthetic')->call('save');
        $category = InventoryCategory::where('name', $name)->first();
        if ($category) {
            $this->own('categories', $category->id);
        }
        $form->assertHasNoErrors();
        $this->assertNotNull($category);
        $this->assertSame(1, $category->auditEvents()->where('event_type', 'created')->count());
        Livewire::test(CategoryForm::class)->set('name', $name)->call('save')->assertHasErrors('name');
        $old = Livewire::test(CategoryForm::class, ['category' => $category]);
        Livewire::test(CategoryForm::class, ['category' => $category])->set('description', 'Synthetic updated')->set('reason', 'Synthetic update')->call('save')->assertHasNoErrors();
        $old->set('description', 'Old form')->set('reason', 'Synthetic stale')->call('save')->assertHasErrors('name');
        Livewire::test(CategoryForm::class, ['category' => $category->fresh()])->set('isActive', false)->set('reason', 'Synthetic archive')->call('save')->assertHasNoErrors();
        $this->assertFalse($category->fresh()->is_active);
        $this->assertSame(3, $category->auditEvents()->count());
        $this->assertFalse(Gate::forUser($this->actor(User::ROLE_SUPER_ADMIN))->allows('delete', $category));
    }

    public function test_unit_livewire_crud_version_condition_and_history_protection(): void
    {
        $this->actingAs($this->actor());
        $code = $this->prefix.'-FORM';
        $form = Livewire::test(UnitForm::class);
        if (EquipmentPhotos::enabled()) {
            $form->set('photo', UploadedFile::fake()->image('camera.png'));
        }
        $form = $form->set('inventory_code', $code)->set('name', $code)->set('category_id', $this->category->id)->set('storage_location', 'Synthetic shelf')->call('save');
        $unit = InventoryUnit::where('name', $code)->first();
        if ($unit) {
            $this->own('units', $unit->id);
        } $form->assertHasNoErrors();
        $this->assertNotNull($unit);
        $old = Livewire::test(UnitForm::class, ['unit' => $unit]);
        Livewire::test(UnitForm::class, ['unit' => $unit])->set('condition', 'minor_damage')->set('reason', 'Synthetic damage')->call('save')->assertHasNoErrors();
        $old->set('name', 'Stale synthetic')->set('reason', 'Synthetic stale')->call('save')->assertHasErrors('name');
        $this->assertSame(2, $unit->fresh()->version);
        $this->rejected(fn () => app(EquipmentLoanCheckout::class)->checkout($this->actor(), $this->token(), $this->input([$unit])));
        Livewire::test(UnitForm::class, ['unit' => $unit->fresh()])->set('condition', 'good')->set('reason', 'Synthetic repaired')->call('save')->assertHasNoErrors();
        $loan = $this->checkout([$unit]);
        Livewire::test(UnitForm::class, ['unit' => $unit->fresh()])->set('name', 'Blocked borrowed')->set('reason', 'Synthetic')->call('save')->assertHasErrors('name');
        $this->recordReturn($this->actor(), $this->token(), $this->returnInput($loan, $loan->items->all()));
        Livewire::test(UnitForm::class, ['unit' => $unit->fresh()])->set('inventory_code', $code.'-X')->set('reason', 'Synthetic')->call('save')->assertHasErrors('inventory_code');
        Livewire::test(UnitForm::class, ['unit' => $unit->fresh()])->set('is_active', false)->set('reason', 'Synthetic archive')->call('save')->assertHasNoErrors();
        $this->assertFalse($unit->fresh()->is_active);
        $this->assertSame($unit->inventory_code, $loan->items->first()->unit_snapshot['inventory_code']);
        $this->assertFalse(Gate::forUser($this->actor(User::ROLE_SUPER_ADMIN))->allows('delete', $unit));
    }

    public function test_checkout_multiunit_atomic_conflict_and_receipt_idempotency(): void
    {
        $token = $this->token();
        $input = $this->input([$this->units[1], $this->units[0]]);
        $service = app(EquipmentLoanCheckout::class);
        $loan = $service->checkout($this->actor(), $token, $input);
        $this->assertSame($loan->id, $service->checkout($this->actor(), $token, $input)->id);
        $this->assertSame(2, $loan->items()->count());
        $this->assertSame(1, $loan->auditEvents()->where('event_type', 'checkout')->count());
        $this->rejected(fn () => $service->checkout($this->actor(User::ROLE_SUPER_ADMIN), $token, $input));
        $this->rejected(fn () => $service->checkout($this->actor(), $token, [...$input, 'purpose' => 'Different synthetic payload']));
        $this->rejected(fn () => $service->checkout($this->actor(), $this->token(), $this->input([$this->units[2], $this->units[0]])));
        $this->assertSame(1, EquipmentLoan::whereIn('submission_token', $this->owned['tokens'])->count());
        $this->assertFalse($this->units[2]->loanItems()->exists());
        $this->assertSame('UTC', $loan->created_at->timezoneName);
        $this->assertMatchesRegularExpression('/\.\d{6}$/', $loan->getRawOriginal('created_at'));
        $this->assertSame(0, $this->primary->transactionLevel());
    }

    public function test_partial_full_return_retry_damage_and_stale_batches(): void
    {
        $loan = $this->checkout([$this->units[0], $this->units[1]]);
        $items = $loan->items()->orderBy('id')->get();
        $service = app(EquipmentLoanReturn::class);
        $input = $this->returnInput($loan, [$items[0]], 'minor_damage');
        $token = $this->token();
        $receipt = $this->recordReturn($this->actor(), $token, $input);
        $this->assertSame($receipt->id, $this->recordReturn($this->actor(), $token, $input)->id);
        $this->assertSame(1, $loan->items()->whereNull('returned_at')->count());
        $this->assertNull($items[0]->fresh()->active_unit_id);
        $this->assertNotNull($items[1]->fresh()->active_unit_id);
        $this->assertSame('minor_damage', $this->units[0]->fresh()->condition);
        $this->rejected(fn () => $this->recordReturn($this->actor(), $this->token(), [...$this->returnInput($loan, [$items[1]]), 'expected_version' => 1]), 'EQ_STALE');
        $this->recordReturn($this->actor(), $this->token(), $this->returnInput($loan, [$items[1]]));
        $this->assertSame(0, $loan->items()->whereNull('returned_at')->count());
        $this->assertSame(2, $loan->auditEvents()->where('event_type', 'returned')->count());
        $this->assertTrue($items[0]->fresh()->due_at_on_return->equalTo($loan->due_at));
        $this->rejected(fn () => $this->recordReturn($this->actor(), $this->token(), $this->returnInput($loan, [$items[0]])));
        $this->rejected(fn () => app(EquipmentLoanCheckout::class)->checkout($this->actor(), $this->token(), $this->input([$this->units[0]])));
    }

    public function test_loss_recovery_and_super_admin_inspection_keep_history(): void
    {
        $loan = $this->checkout([$this->units[0], $this->units[1]]);
        $item = $loan->items()->first();
        $input = $this->lossInput($loan, [$item]);
        $token = $this->token();
        $service = app(EquipmentLoanLoss::class);
        $receipt = $service->record($this->actor(), $token, $input);
        $this->assertSame($receipt->id, $service->record($this->actor(), $token, $input)->id);
        $this->assertNotNull($item->fresh()->active_unit_id);
        $this->assertNull($item->fresh()->returned_at);
        $this->assertFalse($this->units[0]->fresh()->is_active);
        $this->rejected(fn () => app(EquipmentLoanExtension::class)->record($this->actor(), $this->token(), ['loan_id' => $loan->id, 'expected_version' => $loan->fresh()->version, 'new_due_at' => now('Asia/Jakarta')->addDays(2)->format('Y-m-d\TH:i'), 'reason' => 'Synthetic']));
        $this->recordReturn($this->actor(), $this->token(), $this->returnInput($loan, [$item]));
        $unit = $this->units[0]->fresh();
        $this->assertFalse($unit->is_active);
        $this->assertNotNull($item->fresh()->lost_at);
        $this->assertNull($item->fresh()->active_unit_id);
        $this->rejected(fn () => app(InventoryUnitRecovery::class)->reactivate($this->actor(), $unit->id, $unit->version, 'Synthetic inspection', true));
        $restored = app(InventoryUnitRecovery::class)->reactivate($this->actor(User::ROLE_SUPER_ADMIN), $unit->id, $unit->version, 'Synthetic inspected', true);
        $this->assertTrue($restored->is_active);
        $this->assertNotNull($item->fresh()->lost_at);
    }

    public function test_extension_correction_due_history_and_late_returns(): void
    {
        $loan = $this->checkout([$this->units[0]], true);
        $initial = $loan->initial_due_at->format('Y-m-d H:i:s.u');
        $input = ['loan_id' => $loan->id, 'expected_version' => 1, 'new_due_at' => now('Asia/Jakarta')->addDays(2)->format('Y-m-d\TH:i'), 'reason' => 'Synthetic extension'];
        $token = $this->token();
        $receipt = app(EquipmentLoanExtension::class)->record($this->actor(), $token, $input);
        $this->assertTrue($receipt->after_data['was_overdue']);
        $this->assertSame($initial, $loan->fresh()->initial_due_at->format('Y-m-d H:i:s.u'));
        $this->assertSame($receipt->id, app(EquipmentLoanExtension::class)->record($this->actor(), $token, $input)->id);
        $correction = ['loan_id' => $loan->id, 'expected_version' => $loan->fresh()->version, 'borrower_name' => 'Corrected synthetic', 'student_number' => 'SYNSTUDENT-'.substr($this->prefix, 5), 'study_program' => 'Synthetic corrected', 'whatsapp_number' => '620000000002', 'notes' => 'Synthetic corrected notes', 'reason' => 'Synthetic correction'];
        $this->rejected(fn () => app(EquipmentLoanCorrection::class)->record($this->actor(), $this->token(), $correction));
        $ct = $this->token();
        $cr = app(EquipmentLoanCorrection::class)->record($this->actor(User::ROLE_SUPER_ADMIN), $ct, $correction);
        $this->assertSame($cr->id, app(EquipmentLoanCorrection::class)->record($this->actor(User::ROLE_SUPER_ADMIN), $ct, $correction)->id);
        $this->rejected(fn () => app(EquipmentLoanCorrection::class)->record($this->actor(User::ROLE_SUPER_ADMIN), $this->token(), [...$correction, 'due_at' => '2030-01-01']));
        $this->recordReturn($this->actor(), $this->token(), $this->returnInput($loan, $loan->items->all()));
        $this->assertTrue($loan->items()->first()->due_at_on_return->equalTo($loan->fresh()->due_at));
        $late = $this->checkout([$this->units[1]], true);
        $withoutNotes = $this->returnInput($late, $late->items->all());
        $withoutNotes['items'][0]['return_notes'] = null;
        $this->rejected(fn () => $this->recordReturn($this->actor(), $this->token(), $withoutNotes));
        $this->assertNull($late->items()->first()->returned_at);
    }

    public function test_audit_failure_rolls_back_checkout_and_every_lifecycle_write(): void
    {
        $loan = $this->checkout([$this->units[0]]);
        $item = $loan->items()->first();
        $operations = [fn () => app(EquipmentLoanCheckout::class)->checkout($this->actor(), $this->token(), $this->input([$this->units[1]])), fn () => $this->recordReturn($this->actor(), $this->token(), $this->returnInput($loan, [$item])), fn () => app(EquipmentLoanLoss::class)->record($this->actor(), $this->token(), $this->lossInput($loan, [$item])), fn () => app(EquipmentLoanExtension::class)->record($this->actor(), $this->token(), ['loan_id' => $loan->id, 'expected_version' => 1, 'new_due_at' => now('Asia/Jakarta')->addDays(2)->format('Y-m-d\TH:i'), 'reason' => 'Synthetic'])];
        foreach ($operations as $operation) {
            $before = [$loan->fresh()->getAttributes(), $item->fresh()->getAttributes(), $this->units[0]->fresh()->getAttributes(), EquipmentAuditEvent::whereIn('actor_id', $this->owned['users'])->count()];
            EquipmentAuditEvent::creating(function (): void {
                throw new RuntimeException('Synthetic audit failure');
            });
            try {
                $this->rejected($operation, 'EQ_UNEXPECTED');
            } finally {
                EquipmentAuditEvent::flushEventListeners();
            }
            $this->assertSame($before, [$loan->fresh()->getAttributes(), $item->fresh()->getAttributes(), $this->units[0]->fresh()->getAttributes(), EquipmentAuditEvent::whereIn('actor_id', $this->owned['users'])->count()]);
            $this->assertSame(0, $this->primary->transactionLevel());
        }
        $this->assertFalse($this->units[1]->loanItems()->exists());
    }

    public function test_report_filters_csv_privacy_and_export_receipt(): void
    {
        $this->withReportClock('2026-11-11 12:00:00', function (): void {
            $loan = $this->checkout([$this->units[0], $this->units[1]]);
            $this->recordReturn($this->actor(), $this->token(), $this->returnInput($loan, [$loan->items()->first()]));
            $service = app(EquipmentReports::class);
            $filters = ['period' => 'today', 'categoryId' => (string) $this->category->id, 'dateBasis' => 'borrowed_at', 'condition' => 'all', 'status' => 'partially_returned'];
            $page = $service->page($this->actor(), $filters, 10);
            $this->assertSame(2, $page['loanRows']->total());
            $response = $service->download($this->actor(), $filters, 'loans');
            ob_start();
            $response->sendContent();
            $csv = ob_get_clean();
            $this->assertStringContainsString($loan->loan_code, $csv);
            $this->assertStringContainsString('Asia/Jakarta', $csv);
            foreach ([$loan->student_number, '620000000001', 'SYNTHETIC_PRIVATE_'.$this->prefix, $loan->borrower_name] as $secret) {
                $this->assertStringNotContainsString($secret, $csv);
            }
            $audit = EquipmentAuditEvent::where('actor_id', $this->actor()->id)->where('event_type', 'report_exported')->sole();
            $this->assertSame(2, $audit->after_data['row_count']);
            $this->assertFalse($audit->after_data['pii_included']);
            foreach (['=1+1', ' +SUM(1)', '@value', "\t-danger"] as $value) {
                $this->assertStringStartsWith("'", EquipmentReports::csvCell($value));
            }
            $this->rejected(fn () => $service->download($this->actor(User::ROLE_NEWS), $filters, 'loans'));
        });
    }

    public function test_today_report_changes_at_wib_midnight_not_utc_midnight(): void
    {
        $mutableClock = Carbon::getTestNow();
        $immutableClock = CarbonImmutable::getTestNow();
        $this->withReportClock('2026-11-11 23:59:59', function (): void {
            $input = $this->input([$this->units[0]]);
            $input['borrowed_at'] = '2026-11-11T23:59';
            $before = app(EquipmentLoanCheckout::class)->checkout($this->actor(), $this->token(), $input);
            $service = app(EquipmentReports::class);
            $filters = ['period' => 'today', 'categoryId' => (string) $this->category->id];
            $this->assertSame([$before->loan_code], $service->page($this->actor(), $filters, 10)['loanRows']->pluck('loan_code')->all());

            $this->withReportClock('2026-11-12 00:00:00', function () use ($before, $service, $filters): void {
                $this->assertSame('2026-11-12', now('Asia/Jakarta')->toDateString());
                $this->assertSame('2026-11-12', CarbonImmutable::now('Asia/Jakarta')->toDateString());
                $this->assertSame(0, $service->page($this->actor(), $filters, 10)['loanRows']->total());
                $input = $this->input([$this->units[1]]);
                $input['borrowed_at'] = '2026-11-12T00:00';
                $after = app(EquipmentLoanCheckout::class)->checkout($this->actor(), $this->token(), $input);
                $page = $service->page($this->actor(), $filters, 10);
                $this->assertSame([$after->loan_code], $page['loanRows']->pluck('loan_code')->all());
                $this->assertSame('2026-11-12', $page['filters']['startDate']);
                $this->assertSame('2026-11-11 16:59:00.000000', $before->getRawOriginal('borrowed_at'));
                $this->assertSame('2026-11-11 17:00:00.000000', $after->getRawOriginal('borrowed_at'));
                $previousDay = [...$filters, 'period' => 'custom', 'startDate' => '2026-11-11', 'endDate' => '2026-11-11'];
                $this->assertSame([$before->loan_code], $service->page($this->actor(), $previousDay, 10)['loanRows']->pluck('loan_code')->all());
            });
            $this->assertSame('2026-11-11 23:59:59', now('Asia/Jakarta')->format('Y-m-d H:i:s'));
        });
        $this->assertEquals($mutableClock, Carbon::getTestNow());
        $this->assertEquals($immutableClock, CarbonImmutable::getTestNow());
    }

    public function test_report_clock_is_restored_when_a_test_operation_throws(): void
    {
        $mutableClock = Carbon::getTestNow();
        $immutableClock = CarbonImmutable::getTestNow();
        try {
            $this->withReportClock('2026-11-12 00:30:00', function (): void {
                throw new RuntimeException('Synthetic clock restoration probe');
            });
            $this->fail('The probe must throw.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Synthetic clock restoration probe', $exception->getMessage());
        }
        $this->assertEquals($mutableClock, Carbon::getTestNow());
        $this->assertEquals($immutableClock, CarbonImmutable::getTestNow());
    }

    public function test_dashboard_kpis_limit_and_no_equipment_queries_for_other_roles(): void
    {
        $before = app(AdminDashboardMetrics::class)->get($this->actor())['equipment']['kpis'];
        $this->checkout([$this->units[0]], true);
        $this->units[1]->condition = 'major_damage';
        $this->units[1]->save();
        $metrics = app(AdminDashboardMetrics::class)->get($this->actor());
        $equipment = $metrics['equipment'];
        $this->assertSame($before['active_units'], $equipment['kpis']['active_units']);
        $this->assertSame($before['available_units'] - 2, $equipment['kpis']['available_units']);
        $this->assertSame($before['borrowed_units'] + 1, $equipment['kpis']['borrowed_units']);
        $this->assertSame($before['damaged_units'] + 1, $equipment['kpis']['damaged_units']);
        $this->assertSame($before['overdue_loans'] + 1, $equipment['kpis']['overdue_loans']);
        foreach (range(1, 5) as $i) {
            $this->checkout([$this->unitFixture()]);
        }
        $this->assertCount(5, app(AdminDashboardMetrics::class)->get($this->actor(User::ROLE_SUPER_ADMIN))['equipment']['due_loans']);
        foreach ([User::ROLE_NEWS, User::ROLE_TVCLUB] as $role) {
            $this->primary->flushQueryLog();
            $this->primary->enableQueryLog();
            try {
                $data = app(AdminDashboardMetrics::class)->get($this->actor($role));
                $queries = $this->primary->getQueryLog();
            } finally {
                $this->primary->disableQueryLog();
                $this->primary->flushQueryLog();
            }$this->assertArrayNotHasKey('equipment', $data);
            foreach ($queries as $query) {
                $this->assertDoesNotMatchRegularExpression('/inventory_|equipment_/', $query['query']);
            }
        }
    }

    public function test_rbac_direct_urls_livewire_mutations_and_livewire_checkout_return(): void
    {
        $diagnostics = [];
        app(ExceptionHandler::class)->map(function (\Throwable $error) use (&$diagnostics) {
            $diagnostics[] = [get_class($error), basename($error->getFile()), $error->getLine()];

            return EquipmentErrors::forHandler($error, request());
        });
        foreach ([User::ROLE_NEWS, User::ROLE_TVCLUB] as $role) {
            $user = $this->actor($role);
            $this->actingAs($user);
            foreach (['admin.inventory.categories.index', 'admin.inventory.units.index', 'admin.equipment-loans.index', 'admin.equipment-loans.create', 'admin.equipment-reports.index'] as $route) {
                $response = $this->get(route($route));
                $this->assertSame(403, $response->getStatusCode(), $route.' '.json_encode($diagnostics));
            }Livewire::actingAs($user)->test(LoanForm::class)->assertStatus(403);
        }
        $user = $this->actor();
        $this->actingAs($user);
        foreach (['admin.inventory.categories.index', 'admin.inventory.units.index', 'admin.equipment-loans.index', 'admin.equipment-reports.index'] as $route) {
            $this->get(route($route))->assertOk();
        }
        $form = Livewire::actingAs($user)->test(LoanForm::class);
        $token = $form->get('submissionToken');
        $this->rememberToken($token);
        // Persist generated UI token before submission, including a failed assertion.
        $input = $this->input([$this->units[0]]);
        unset($input['items']);
        $form->set('data', $input)->set('rows.0.product', $this->units[0]->id)->call('review')->assertHasNoErrors()
            ->set('data.notes', 'Updated after review')->call('save')->assertHasErrors('items');
        $this->assertDatabaseMissing('equipment_loans', ['submission_token' => $token]);
        $form->call('review')->assertHasNoErrors()->call('save')->assertHasNoErrors();
        $loan = EquipmentLoan::where('submission_token', $token)->sole();
        $item = $loan->items()->first();
        $return = Livewire::test(ReturnForm::class, ['loan' => $loan]);
        if (EquipmentPhotos::enabled()) {
            $return->set('photos.'.$item->id, UploadedFile::fake()->image('retur.png'));
        }
        $return->set('returns.'.$item->id.'.condition_in', 'good')->set('returns.'.$item->id.'.return_notes', 'Synthetic return')->call('selectAll')->call('save')->assertHasNoErrors();
        $this->assertSame(0, $loan->items()->whereNull('returned_at')->count());
        Livewire::test(CorrectionForm::class, ['loan' => $loan])->assertStatus(403);
        $mounted = Livewire::test(CategoryForm::class, ['category' => $this->category])->set('description', 'Blocked synthetic mutation')->set('reason', 'Synthetic');
        $user->role_id = $this->actor(User::ROLE_NEWS)->role_id;
        $user->save();
        $user->refresh();
        $mounted->call('save')->assertStatus(403);
        $this->assertNotSame('Blocked synthetic mutation', $this->category->fresh()->description);
    }

    public function test_database_constraints_reject_duplicate_active_units_and_invalid_condition(): void
    {
        $first = $this->checkout([$this->units[0]]);
        $second = $this->checkout([$this->units[1]]);
        try {
            $detail = new EquipmentLoanItem;
            $detail->forceFill(['loan_id' => $second->id, 'inventory_unit_id' => $this->units[0]->id, 'unit_snapshot' => ['name' => 'Synthetic'], 'condition_out' => 'good'])->save();
            $this->fail('Generated unique constraint did not reject duplicate.');
        } catch (QueryException $e) {
            $this->assertSame(1062, (int) $e->errorInfo[1]);
        }
        try {
            $this->units[2]->condition = 'invalid';
            $this->units[2]->save();
            $this->fail('CHECK did not reject invalid condition.');
        } catch (QueryException $e) {
            $this->assertSame(3819, (int) $e->errorInfo[1]);
        }
        $this->assertSame('good', $this->units[2]->fresh()->condition);
        $this->assertSame(1, $second->items()->count());
        $this->assertSame(1, $first->items()->count());
    }

    public function test_correction_and_reactivation_audit_failures_roll_back(): void
    {
        $loan = $this->checkout([$this->units[0]]);
        $before = $loan->fresh()->getAttributes();
        $input = ['loan_id' => $loan->id, 'expected_version' => 1, 'borrower_name' => 'Synthetic corrected', 'student_number' => 'SYNSTUDENT-'.substr($this->prefix, 5), 'study_program' => 'Synthetic corrected', 'whatsapp_number' => '620000000002', 'notes' => 'Synthetic', 'reason' => 'Synthetic correction'];
        EquipmentAuditEvent::creating(function (): void {
            throw new RuntimeException('Synthetic audit failure');
        });
        try {
            $this->rejected(fn () => app(EquipmentLoanCorrection::class)->record($this->actor(User::ROLE_SUPER_ADMIN), $this->token(), $input), 'EQ_UNEXPECTED');
        } finally {
            EquipmentAuditEvent::flushEventListeners();
        }
        $this->assertSame($before, $loan->fresh()->getAttributes());
        $item = $loan->items()->first();
        app(EquipmentLoanLoss::class)->record($this->actor(), $this->token(), $this->lossInput($loan, [$item]));
        $this->recordReturn($this->actor(), $this->token(), $this->returnInput($loan, [$item]));
        $unit = $this->units[0]->fresh();
        $before = $unit->getAttributes();
        EquipmentAuditEvent::creating(function (): void {
            throw new RuntimeException('Synthetic audit failure');
        });
        try {
            $this->rejected(fn () => app(InventoryUnitRecovery::class)->reactivate($this->actor(User::ROLE_SUPER_ADMIN), $unit->id, $unit->version, 'Synthetic inspected', true), 'EQ_UNEXPECTED');
        } finally {
            EquipmentAuditEvent::flushEventListeners();
        }
        $this->assertSame($before, $unit->fresh()->getAttributes());
        $this->assertFalse($unit->fresh()->is_active);
    }

    public function test_news_and_tvclub_cannot_call_transaction_services_or_export(): void
    {
        $loan = $this->checkout([$this->units[0]]);
        $item = $loan->items()->first();
        $version = $loan->version;
        foreach ([User::ROLE_NEWS, User::ROLE_TVCLUB] as $role) {
            $actor = $this->actor($role);
            $this->rejected(fn () => app(EquipmentLoanCheckout::class)->checkout($actor, $this->token(), $this->input([$this->units[1]])));
            $this->rejected(fn () => $this->recordReturn($actor, $this->token(), $this->returnInput($loan, [$item])));
            $this->rejected(fn () => app(EquipmentLoanLoss::class)->record($actor, $this->token(), $this->lossInput($loan, [$item])));
            $this->rejected(fn () => app(EquipmentLoanExtension::class)->record($actor, $this->token(), ['loan_id' => $loan->id, 'expected_version' => 1, 'new_due_at' => now('Asia/Jakarta')->addDays(3)->format('Y-m-d\TH:i'), 'reason' => 'Synthetic']));
            $this->rejected(fn () => app(EquipmentReports::class)->download($actor, [], 'inventory'));
        }
        $this->assertSame($version, $loan->fresh()->version);
        $this->assertNull($item->fresh()->returned_at);
        $this->assertNull($item->fresh()->lost_at);
        $this->assertSame(1, $loan->auditEvents()->count());
    }
}
