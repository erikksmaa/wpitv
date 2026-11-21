<?php

namespace Tests\Unit;

use App\Support\TVClubSocialRecap;
use PHPUnit\Framework\TestCase;

class TVClubSocialRecapTest extends TestCase
{
    public function test_coverage_distinguishes_zero_and_missing_and_totals_remain_exact(): void
    {
        $posts = [
            ['observation' => ['metrics' => ['views' => PHP_INT_MAX, 'likes' => 0]]],
            ['observation' => ['metrics' => ['views' => PHP_INT_MAX, 'likes' => null]]],
            ['observation' => null],
        ];
        $summary = TVClubSocialRecap::summarize($posts);
        $this->assertSame(['total' => '18446744073709551614', 'coverage' => 2], $summary['views']);
        $this->assertSame(['total' => '0', 'coverage' => 1], $summary['likes']);
        $this->assertSame(['total' => null, 'coverage' => 0], $summary['shares']);
        $this->assertArrayNotHasKey('followers', $summary);
    }
}
