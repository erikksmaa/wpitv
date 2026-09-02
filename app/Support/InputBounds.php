<?php

namespace App\Support;

final class InputBounds
{
    public const MAX_SEARCH_LENGTH = 150;

    public static function search(string $value): string
    {
        return mb_substr(trim($value), 0, self::MAX_SEARCH_LENGTH);
    }

    public static function perPage(int $value, int $default = 10): int
    {
        return in_array($value, [10, 25, 50], true) ? $value : $default;
    }

    public static function month(string $value): string
    {
        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value) === 1
            ? $value
            : now()->format('Y-m');
    }
}
