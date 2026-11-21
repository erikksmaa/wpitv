<?php

namespace App\Services;

use App\Models\MonthlyActivityReport;
use App\Models\User;
use App\Support\MonthlyActivityRules as Rules;
use Illuminate\Support\Facades\Gate;
use PDOException;

class MonthlyActivityDashboard
{
    public function get(User $user): ?array
    {
        $gate = Gate::forUser($user);
        if ($gate->denies('viewAny', MonthlyActivityReport::class)) {
            return null;
        }

        $review = $gate->allows('review', new MonthlyActivityReport);
        $divisions = array_filter(Rules::DIVISIONS, fn ($division) => $review || $gate->allows('create', [MonthlyActivityReport::class, $division]));
        $month = now('Asia/Jakarta')->startOfMonth();
        $columns = ['id', 'division', 'period_start', 'status'];
        try {
            $current = MonthlyActivityReport::whereIn('division', $divisions)->where('period_start', $month->toDateString())->get($columns)->keyBy('division');
            $queue = MonthlyActivityReport::where('status', 'submitted');
            $waiting = $review ? $queue->count() : 0;
            $latest = $waiting ? $queue->orderBy('period_start')->orderBy('id')->limit(5)->get($columns) : collect();

            return compact('month', 'divisions', 'current', 'review', 'waiting', 'latest');
        } catch (PDOException) {
            logger()->warning('MONTHLY_DASHBOARD_UNAVAILABLE');

            return ['unavailable' => true];
        }
    }
}
