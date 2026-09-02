<?php

namespace App\Services;

use App\Models\UserCounter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class VisitorStatisticsService
{
    public const PUBLIC_CACHE_SECONDS = 60;

    public function publicMetrics(): ?array
    {
        $now = Carbon::now((string) config('app.timezone', 'Asia/Jakarta'));
        $cacheKey = 'visitor-counter.public.'.$now->toDateString();

        try {
            return Cache::remember(
                $cacheKey,
                self::PUBLIC_CACHE_SECONDS,
                fn (): array => $this->queryPublicMetrics($now),
            );
        } catch (Throwable $exception) {
            Log::warning('Public visitor statistics could not be loaded.', [
                'exception' => $exception,
            ]);

            return null;
        }
    }

    private function queryPublicMetrics(Carbon $now): array
    {
        $today = $now->toDateString();
        $yesterday = $now->copy()->subDay()->toDateString();
        $monthStart = $now->copy()->startOfMonth()->toDateString();
        $yearStart = $now->copy()->startOfYear()->toDateString();
        $onlineThreshold = $now->copy()->subMinutes(5)->timestamp;

        $metrics = UserCounter::query()
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN tanggal = ? THEN hits ELSE 0 END), 0) AS today_hits,
                 COALESCE(SUM(CASE WHEN tanggal = ? THEN hits ELSE 0 END), 0) AS yesterday_hits,
                 COALESCE(SUM(CASE WHEN tanggal BETWEEN ? AND ? THEN hits ELSE 0 END), 0) AS month_hits,
                 COALESCE(SUM(CASE WHEN tanggal BETWEEN ? AND ? THEN hits ELSE 0 END), 0) AS year_hits,
                 COALESCE(SUM(CASE WHEN online BETWEEN ? AND ? THEN 1 ELSE 0 END), 0) AS online_visitors',
                [$today, $yesterday, $monthStart, $today, $yearStart, $today, $onlineThreshold, $now->timestamp],
            )
            ->first();

        return [
            'today' => (int) $metrics->today_hits,
            'yesterday' => (int) $metrics->yesterday_hits,
            'month' => (int) $metrics->month_hits,
            'year' => (int) $metrics->year_hits,
            'online' => (int) $metrics->online_visitors,
        ];
    }
}
