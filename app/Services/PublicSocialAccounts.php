<?php

namespace App\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

final class PublicSocialAccounts
{
    public const CACHE_KEY = 'wpitv.public_social_accounts.v1';

    public function visibilityColumnsReady(): bool
    {
        try {
            return Schema::hasTable('tvclub_social_accounts')
                && Schema::hasColumn('tvclub_social_accounts', 'show_on_website')
                && Schema::hasColumn('tvclub_social_accounts', 'display_order');
        } catch (QueryException) {
            return false;
        }
    }

    /**
     * Return active, public-visible social accounts with safe HTTP/HTTPS URLs.
     * Multiple accounts on the same platform receive disambiguated labels.
     *
     * @return Collection<int, object>
     */
    public function all(): Collection
    {
        $items = Cache::get(self::CACHE_KEY);

        if (! is_array($items)) {
            try {
                $items = DB::table('tvclub_social_accounts')
                    ->whereNull('archived_at')
                    ->where('show_on_website', 1)
                    ->whereNotNull('profile_url')
                    ->where('profile_url', '!=', '')
                    ->whereIn('platform', array_keys(SocialReport::PLATFORMS))
                    ->orderBy('display_order')
                    ->orderBy('id')
                    ->get(['id', 'platform', 'handle', 'display_name', 'profile_url', 'display_order'])
                    ->all();

                Cache::put(self::CACHE_KEY, $items, now()->addMinutes(10));
            } catch (QueryException $error) {
                $code = (string) ($error->errorInfo[0] ?? $error->getCode());
                if (! in_array($code, ['42S02', '42S22', 'HY000'], true)) {
                    report($error);
                }

                return collect();
            }
        }

        $valid = collect($items)->filter(function ($item): bool {
            $url = trim((string) ($item->profile_url ?? ''));
            if ($url === '') {
                return false;
            }

            return ! Validator::make(['url' => $url], ['url' => 'url:http,https'])->fails();
        })->values();

        $platformCounts = $valid->countBy('platform');

        return $valid->map(function ($item) use ($platformCounts): object {
            $platform = (string) $item->platform;
            $platformLabel = SocialReport::PLATFORMS[$platform] ?? ucfirst($platform);
            $displayName = trim((string) ($item->display_name ?? '')) ?: $platformLabel;
            $handle = trim((string) ($item->handle ?? ''));
            $isDuplicatePlatform = ($platformCounts[$platform] ?? 0) > 1;
            $shortHandle = $handle !== '' ? ltrim($handle, '@') : $displayName;

            return (object) [
                'id' => (int) $item->id,
                'platform' => $platform,
                'platform_label' => $platformLabel,
                'display_name' => $displayName,
                'handle' => $handle,
                'url' => trim((string) $item->profile_url),
                'display_order' => (int) $item->display_order,
                'has_multiple_on_platform' => $isDuplicatePlatform,
                'short_label' => $displayName !== '' ? $displayName : ($shortHandle !== '' ? '@'.$shortHandle : $platformLabel),
                'aria_label' => $isDuplicatePlatform
                    ? "{$platformLabel}: {$displayName}".($handle !== '' ? " ({$handle})" : '')
                    : "{$platformLabel} WPiTV — {$displayName}",
            ];
        });
    }

    public function primaryByPlatform(string $platform): ?object
    {
        return $this->all()->firstWhere('platform', $platform);
    }

    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
