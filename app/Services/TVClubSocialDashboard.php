<?php

namespace App\Services;

use App\Models\TVClubSocialAccount;
use App\Models\TVClubSocialReportVersion;
use App\Models\User;
use App\Support\TVClubSocialRules as Rules;
use Illuminate\Support\Facades\DB;
use Throwable;

class TVClubSocialDashboard
{
    public function get(User $actor, string $month): ?array
    {
        if (! $actor->can('viewAny', TVClubSocialReportVersion::class)) {
            return null;
        }

        try {
            $start = Rules::period($month);
            $end = $start->addMonth()->utc()->format('Y-m-d H:i:s.u');
            $begin = $start->utc()->format('Y-m-d H:i:s.u');
            $closing = DB::table('tvclub_social_post_observations as candidate')->select('candidate.id')
                ->whereColumn('candidate.post_id', 'post.id')->where('candidate.observed_at', '<', $end)
                ->orderByDesc('candidate.observed_at')->orderByDesc('candidate.id')->limit(1);
            $query = DB::table('tvclub_social_posts as post')->join('tvclub_social_accounts as account', 'account.id', '=', 'post.account_id')
                ->where('post.published_at', '>=', $begin)->where('post.published_at', '<', $end)
                ->groupBy('account.platform')->select('account.platform')->selectRaw('COUNT(*) AS posts');
            $followers = collect();
            $active = null;
            if ($actor->isTVClub()) {
                $query->leftJoin('tvclub_social_post_observations as observation', fn ($join) => $join->where('observation.id', '=', $closing));
                foreach (Rules::POST_METRICS as $metric) {
                    $query->selectRaw("SUM(observation.$metric) AS $metric, COUNT(observation.$metric) AS {$metric}_coverage");
                }
                $accounts = TVClubSocialAccount::query()->select('id', 'platform', 'handle')
                    ->addSelect(['closing_id' => DB::table('tvclub_social_account_observations')->select('id')
                        ->whereColumn('account_id', 'tvclub_social_accounts.id')->where('observed_at', '<', $end)
                        ->orderByDesc('observed_at')->orderByDesc('id')->limit(1)])
                    ->where(fn ($query) => $query->whereNull('archived_at')->orWhereHas('observations', fn ($query) => $query->where('observed_at', '<', $end)))
                    ->orderBy('platform')->orderBy('id')->limit(10)->get();
                $observations = DB::table('tvclub_social_account_observations')->whereIn('id', $accounts->pluck('closing_id')->filter())
                    ->get(['id', 'followers', 'observed_at'])->keyBy('id');
                $followers = $accounts->map(fn ($account) => ['account' => $account, 'observation' => $observations->get($account->closing_id)]);
                $active = TVClubSocialAccount::where('created_at', '<', $end)
                    ->where(fn ($query) => $query->whereNull('archived_at')->orWhere('archived_at', '>=', $begin))->count();
            }
            $platforms = $query->get()->keyBy('platform');
            $version = TVClubSocialReportVersion::where('period_start', $start->toDateString())->orderByDesc('revision_number')
                ->first(['id', 'revision_number', 'published_at']);

            return compact('month', 'platforms', 'followers', 'active', 'version');
        } catch (Throwable) {
            return ['unavailable' => true];
        }
    }
}
