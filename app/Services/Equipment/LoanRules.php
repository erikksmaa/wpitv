<?php

namespace App\Services\Equipment;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final class LoanRules
{
    public static function wib(?string $timestamp): string
    {
        return $timestamp ? CarbonImmutable::parse($timestamp, 'UTC')->timezone('Asia/Jakarta')->format('d/m/Y H:i').' WIB' : '—';
    }

    public static function late(string $dueDate, CarbonInterface $event): bool
    {
        return $event->copy()->timezone('Asia/Jakarta')->toDateString() > $dueDate;
    }

    public static function progress(array $outcomes): string
    {
        if (! $outcomes || array_diff($outcomes, ['on_loan', 'returned', 'lost', 'voided'])) {
            return 'integrity_error';
        }
        $counts = array_count_values($outcomes);
        if (isset($counts['voided'])) {
            return $counts['voided'] === count($outcomes) ? 'voided' : 'integrity_error';
        }
        if (($counts['on_loan'] ?? 0) === count($outcomes)) {
            return 'active';
        }

        return isset($counts['on_loan']) ? 'partially_resolved' : 'closed';
    }

    public static function canonical(array $data): array
    {
        if (! array_is_list($data)) {
            ksort($data);
        }
        foreach ($data as &$value) {
            if (is_array($value)) {
                $value = self::canonical($value);
            }
        }

        return $data;
    }

    public static function progressSql(): string
    {
        return "CASE WHEN COUNT(*)=0 THEN 'integrity_error'
            WHEN SUM(outcome='voided')=COUNT(*) THEN 'voided'
            WHEN SUM(outcome='voided')>0 THEN 'integrity_error'
            WHEN SUM(outcome='on_loan')=COUNT(*) THEN 'active'
            WHEN SUM(outcome='on_loan')>0 THEN 'partially_resolved'
            ELSE 'closed' END";
    }
}
