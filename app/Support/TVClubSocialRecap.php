<?php

namespace App\Support;

final class TVClubSocialRecap
{
    public static function summarize(array $posts): array
    {
        $summary = [];
        foreach (TVClubSocialRules::POST_METRICS as $metric) {
            $total = null;
            $coverage = 0;
            foreach ($posts as $post) {
                $value = $post['observation']['metrics'][$metric] ?? null;
                if ($value !== null) {
                    $total = self::add($total ?? '0', (string) $value);
                    $coverage++;
                }
            }
            $summary[$metric] = ['total' => $total, 'coverage' => $coverage];
        }

        return $summary;
    }

    private static function add(string $left, string $right): string
    {
        $left = strrev($left);
        $right = strrev($right);
        $carry = 0;
        $result = '';
        for ($i = 0, $length = max(strlen($left), strlen($right)); $i < $length; $i++) {
            $digit = (int) ($left[$i] ?? '0') + (int) ($right[$i] ?? '0') + $carry;
            $result .= $digit % 10;
            $carry = intdiv($digit, 10);
        }

        return strrev($result.($carry ? '1' : ''));
    }
}
