<?php

namespace Tests\Unit;

use App\Exceptions\MonthlyActivityException;
use App\Models\MonthlyActivityEvent;
use App\Models\MonthlyActivityReport;
use App\Models\MonthlyActivityVersion;
use App\Models\Role;
use App\Models\User;
use App\Policies\MonthlyActivityReportPolicy;
use App\Support\MonthlyActivityRules as Rules;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Illuminate\Validation\ValidationException;
use LogicException;
use PHPUnit\Framework\TestCase;

class MonthlyActivityFoundationTest extends TestCase
{
    public function test_period_is_first_day_in_wib_and_handles_year_rollover(): void
    {
        $period = Rules::period('2026-12');
        $this->assertSame('2026-12-01 00:00:00 +07:00', $period->format('Y-m-d H:i:s P'));
        $this->assertSame('2026-12-31 17:00:00', $period->addMonth()->utc()->format('Y-m-d H:i:s'));
    }

    public function test_validation_rejects_invalid_values_and_requires_explicit_nil_reason(): void
    {
        $previous = Facade::getFacadeApplication();
        $container = new Container;
        $container->instance('validator', new Factory(new Translator(new ArrayLoader, 'en'), $container));
        Facade::clearResolvedInstance('validator');
        Facade::setFacadeApplication($container);
        try {
            foreach ([
                'period' => fn () => Rules::period('2026-13'),
                'division' => fn () => Rules::division('tvclub'),
                'version' => fn () => Rules::version(1, 0),
                'nil_reason' => fn () => Rules::reason('  ', 'nil_reason', true),
                'reason' => fn () => Rules::reason(str_repeat('a', 1001), 'reason', true),
            ] as $field => $operation) {
                try {
                    $operation();
                    $this->fail('Invalid input accepted.');
                } catch (ValidationException $exception) {
                    $this->assertArrayHasKey($field, $exception->errors());
                }
            }
            $this->assertSame('Tidak ada kegiatan', Rules::reason(' Tidak ada kegiatan ', 'nil_reason', true));
        } finally {
            Facade::clearResolvedInstance('validator');
            Facade::setFacadeApplication($previous);
        }
    }

    public function test_only_supported_transitions_and_exact_version_are_accepted(): void
    {
        $allowed = ['draft:submitted', 'revision:submitted', 'submitted:revision', 'submitted:approved'];
        foreach (['draft', 'submitted', 'revision', 'approved'] as $from) {
            foreach (['draft', 'submitted', 'revision', 'approved'] as $to) {
                $accepted = true;
                try {
                    Rules::transition($from, $to);
                } catch (MonthlyActivityException $exception) {
                    $accepted = false;
                    $this->assertSame('MONTHLY_STATE', $exception->errorCode);
                }
                $this->assertSame(in_array($from.':'.$to, $allowed, true), $accepted);
            }
        }
        Rules::version(3, 3);
        $this->expectExceptionMessage('Laporan telah berubah');
        Rules::version(3, 2);
    }

    public function test_policy_preserves_division_and_review_boundaries_for_all_roles(): void
    {
        $policy = new MonthlyActivityReportPolicy;
        foreach (User::ADMIN_ROLES as $name) {
            foreach ([true, false] as $active) {
                $user = (new User)->forceFill(['status' => $active])->setRelation('role', (new Role)->forceFill(['name' => $name]));
                $reviewer = $active && $name === User::ROLE_SUPER_ADMIN;
                $this->assertSame($active && $name !== User::ROLE_TVCLUB, $policy->viewAny($user));
                foreach (['production' => User::ROLE_PRODUCTION, 'news' => User::ROLE_NEWS] as $division => $author) {
                    $report = (new MonthlyActivityReport)->forceFill(['division' => $division]);
                    $ownsDivision = $active && $name === $author;
                    $this->assertSame($ownsDivision, $policy->create($user, $division));
                    $this->assertSame($reviewer || $ownsDivision, $policy->view($user, $report));
                    $this->assertSame($ownsDivision, $policy->update($user, $report));
                    $this->assertSame($ownsDivision, $policy->submit($user, $report));
                    $this->assertSame($reviewer, $policy->review($user, $report));
                    $this->assertFalse($policy->delete($user, $report));
                }
            }
        }
    }

    public function test_history_rejects_quiet_writes_and_deletes_before_db_access(): void
    {
        foreach ([new MonthlyActivityVersion, new MonthlyActivityEvent] as $model) {
            $model->exists = true;
            foreach (['saveQuietly', 'deleteQuietly', 'forceDelete', 'increment'] as $method) {
                try {
                    $method === 'increment' ? $model->increment('revision_number') : $model->$method();
                    $this->fail('History mutation accepted.');
                } catch (LogicException $exception) {
                    $this->assertStringContainsString('Monthly activity', $exception->getMessage());
                }
            }
        }
    }

    public function test_snapshot_hash_ignores_json_object_key_order_but_preserves_list_order(): void
    {
        $snapshot = ['period' => '2026-11', 'items' => [['title' => 'A', 'id' => 1], ['title' => 'B', 'id' => 2]]];
        $reordered = ['items' => [['id' => 1, 'title' => 'A'], ['id' => 2, 'title' => 'B']], 'period' => '2026-11'];
        $this->assertSame(Rules::snapshotHash($snapshot), Rules::snapshotHash($reordered));
        $reordered['items'] = array_reverse($reordered['items']);
        $this->assertNotSame(Rules::snapshotHash($snapshot), Rules::snapshotHash($reordered));
    }
}
