<?php

namespace App\Services;

use App\Models\InventoryUnit;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

final class InventoryCode
{
    public static function issue(InventoryUnit $unit): void
    {
        $normalize = static fn (string $value) => preg_replace('/[^A-Z0-9]/', '', strtoupper(Str::ascii($value)));
        $brand = substr($normalize($unit->brand ?: $unit->name), 0, 3) ?: 'INV';
        $model = substr($normalize($unit->model ?: $unit->name), 0, 20) ?: 'UNIT';
        $base = $brand.'-'.$model.'-'.str_pad((string) $unit->id, 3, '0', STR_PAD_LEFT);
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $unit->inventory_code = $base.($attempt ? '-'.$attempt : '');
            try {
                $unit->save();

                return;
            } catch (QueryException $e) {
                if (($e->errorInfo[1] ?? null) !== 1062 || $attempt === 2) {
                    throw $e;
                }
            }
        }
    }
}
