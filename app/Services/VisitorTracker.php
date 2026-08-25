<?php

namespace App\Services;

use App\Models\UserCounter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VisitorTracker
{
    /**
     * Track a visitor by IP address and current date.
     * Safely increments hit count and updates active online timestamp.
     *
     * @param  Request|string|null  $requestOrIp
     */
    public static function track(mixed $requestOrIp = null): ?UserCounter
    {
        try {
            $request = $requestOrIp instanceof Request ? $requestOrIp : request();

            // Prevent double-tracking on Livewire AJAX component updates or internal calls
            if ($request && ($request->header('X-Livewire') || $request->is('livewire/*'))) {
                return null;
            }

            $ip = null;
            if ($requestOrIp instanceof Request) {
                $ip = $requestOrIp->ip();
            } elseif (is_string($requestOrIp) && ! empty($requestOrIp)) {
                $ip = $requestOrIp;
            } elseif ($request) {
                $ip = $request->ip();
            }

            $ip = $ip ?: '127.0.0.1';
            $today = now()->toDateString();
            $timestamp = time();

            // Query existing record for this IP on today's date
            $record = UserCounter::where('ip', $ip)
                ->where('tanggal', $today)
                ->first();

            if ($record) {
                $record->hits = $record->hits + 1;
                $record->online = $timestamp;
                $record->save();
            } else {
                $record = UserCounter::create([
                    'ip' => $ip,
                    'tanggal' => $today,
                    'hits' => 1,
                    'online' => $timestamp,
                ]);
            }

            return $record;
        } catch (\Throwable $e) {
            // Visitor counter failure must NEVER crash the public website (no HTTP 500)
            Log::warning('Visitor tracking failed: '.$e->getMessage(), [
                'exception' => $e,
            ]);

            return null;
        }
    }

    /**
     * Get full aggregated stats array for dashboard and widgets.
     */
    public static function getStats(): array
    {
        $today = now()->toDateString();
        $onlineThreshold = time() - 300;
        $stats = UserCounter::query()
            ->selectRaw(
                'COALESCE(SUM(hits), 0) as total_hits,
                COUNT(*) as total_visitors,
                COALESCE(SUM(CASE WHEN tanggal = ? THEN hits ELSE 0 END), 0) as today_hits,
                COALESCE(SUM(CASE WHEN tanggal = ? THEN 1 ELSE 0 END), 0) as today_visitors,
                COALESCE(SUM(CASE WHEN online > ? THEN 1 ELSE 0 END), 0) as online_visitors',
                [$today, $today, $onlineThreshold]
            )
            ->first();

        return [
            'total_hits' => (int) $stats->total_hits,
            'total_visitors' => (int) $stats->total_visitors,
            'today_hits' => (int) $stats->today_hits,
            'today_visitors' => (int) $stats->today_visitors,
            'online_visitors' => (int) $stats->online_visitors,
        ];
    }
}
