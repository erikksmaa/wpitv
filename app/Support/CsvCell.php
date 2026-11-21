<?php

namespace App\Support;

final class CsvCell
{
    public static function sanitize(string|int|float|null $value): string
    {
        $text = (string) $value;
        $dangerous = preg_match('/\A(?:[\p{Z}\p{C}\s]*[=+@-]|[\p{C}])/u', $text);

        return $dangerous !== 0 ? "'".$text : $text;
    }
}
