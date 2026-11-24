<?php

namespace Tests\Unit;

use App\Models\Role;
use App\Models\User;
use App\Services\Equipment\LoanRules;
use App\Support\EquipmentPermissions;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EquipmentLoanRulesTest extends TestCase
{
    public static function progressCases(): array
    {
        return [
            [[], 'integrity_error'], [['on_loan'], 'active'], [['on_loan', 'on_loan'], 'active'],
            [['returned', 'on_loan'], 'partially_resolved'], [['lost', 'on_loan'], 'partially_resolved'],
            [['returned', 'lost'], 'closed'], [['returned'], 'closed'], [['lost'], 'closed'],
            [['voided'], 'voided'], [['voided', 'voided'], 'voided'], [['voided', 'on_loan'], 'integrity_error'],
            [['voided', 'returned'], 'integrity_error'], [['other'], 'integrity_error'],
        ];
    }

    #[DataProvider('progressCases')]
    public function test_progress_is_derived_from_all_outcomes(array $outcomes, string $expected): void
    {
        $this->assertSame($expected, LoanRules::progress($outcomes));
    }

    public static function timeCases(): array
    {
        return [
            ['2026-11-24 16:59:59.999999', false], ['2026-11-24 17:00:00', true],
            ['2026-11-24 00:00:00', false], ['2026-11-25 00:00:00', true], ['2026-11-23 17:00:00', false],
        ];
    }

    #[DataProvider('timeCases')]
    public function test_due_date_uses_end_of_wib_day(string $utc, bool $late): void
    {
        $this->assertSame($late, LoanRules::late('2026-11-24', CarbonImmutable::parse($utc, 'UTC')));
    }

    public function test_canonical_payload_orders_object_keys_but_keeps_photo_sequence(): void
    {
        $a = ['items' => [['notes' => 'x', 'id' => 1]], 'photos' => ['B', 'A']];
        $b = ['photos' => ['B', 'A'], 'items' => [['id' => 1, 'notes' => 'x']]];
        $this->assertSame(LoanRules::canonical($a), LoanRules::canonical($b));
        $this->assertNotSame(LoanRules::canonical($a), LoanRules::canonical(array_replace($a, ['photos' => ['A', 'B']])));
    }

    public function test_loan_permissions_are_explicit_and_void_is_not_a_production_bypass(): void
    {
        foreach ([User::ROLE_SUPER_ADMIN, User::ROLE_PRODUCTION, User::ROLE_NEWS, User::ROLE_TVCLUB, 'Unknown'] as $role) {
            $user = new User(['status' => true]);
            $user->setRelation('role', new Role(['name' => $role]));
            $this->assertSame(in_array($role, [User::ROLE_SUPER_ADMIN, User::ROLE_PRODUCTION], true), EquipmentPermissions::allows($user, 'equipment.loan.checkout'));
            $this->assertSame($role === User::ROLE_SUPER_ADMIN, EquipmentPermissions::allows($user, 'equipment.loan.void'));
            $user->status = false;
            $this->assertFalse(EquipmentPermissions::allows($user, 'equipment.loan.return'));
        }
    }

    public function test_utc_event_display_is_wib(): void
    {
        $this->assertSame('25/11/2026 00:00 WIB', LoanRules::wib('2026-11-24 17:00:00'));
    }
}
