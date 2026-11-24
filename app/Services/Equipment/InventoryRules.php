<?php

namespace App\Services\Equipment;

use Illuminate\Validation\ValidationException;

final class InventoryRules
{
    public static function transition(string $current, string $action, string $condition, bool $borrowed): string
    {
        $next = match ($action) {
            'unit.maintenance_start' => in_array($current, ['active'], true) ? 'maintenance' : null,
            'unit.maintenance_finish' => $current === 'maintenance' && $condition === 'good' ? 'active' : null,
            'unit.lost' => in_array($current, ['active', 'maintenance'], true) ? 'lost' : null,
            'unit.found' => $current === 'lost' ? 'maintenance' : null,
            'unit.retire' => in_array($current, ['active', 'maintenance', 'lost'], true) ? 'retired' : null,
            default => null,
        };
        if ($borrowed || $next === null) {
            throw ValidationException::withMessages(['action' => 'Transisi tidak diizinkan. Unit dipinjam tidak boleh diubah; retired terminal dan found hanya lost → maintenance.']);
        }

        return $next;
    }

    public static function reserve(int $next, int $quantity): array
    {
        if ($next < 1 || $quantity < 1 || $quantity > 100 || $next + $quantity > 1000000) {
            throw ValidationException::withMessages(['quantity' => 'Stok 1–100 unit per operasi; kapasitas nomor unit telah terlampaui.']);
        }

        return range($next, $next + $quantity - 1);
    }

    public static function assetCode(string $code, int $number): string
    {
        return $code.'-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT);
    }
}
