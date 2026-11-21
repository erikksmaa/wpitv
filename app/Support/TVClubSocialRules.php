<?php

namespace App\Support;

use App\Exceptions\TVClubSocialException;
use Carbon\CarbonImmutable;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

final class TVClubSocialRules
{
    public const TYPES = [
        'tiktok' => ['short_video', 'photo', 'other'],
        'instagram' => ['reel', 'photo', 'carousel', 'story', 'other'],
        'facebook' => ['reel', 'video', 'photo', 'link', 'text', 'other'],
    ];

    private const HOSTS = [
        'tiktok' => ['www.tiktok.com', 'tiktok.com'],
        'instagram' => ['www.instagram.com', 'instagram.com'],
        'facebook' => ['www.facebook.com', 'facebook.com', 'm.facebook.com'],
    ];

    public const POST_METRICS = ['views', 'likes', 'comments', 'shares'];

    public const ACCOUNT_METRICS = ['followers'];

    public static function platform(string $platform): string
    {
        if (! isset(self::TYPES[$platform])) {
            self::invalid('platform', 'Platform tidak didukung.');
        }

        return $platform;
    }

    public static function contentType(string $platform, string $type): string
    {
        if (! in_array($type, self::TYPES[self::platform($platform)], true)) {
            self::invalid('content_type', 'Jenis konten tidak tersedia untuk platform ini.');
        }

        return $type;
    }

    public static function postUrl(string $platform, string $url): array
    {
        [$host, $path, $query] = self::parseUrl($platform, $url);
        $patterns = match ($platform) {
            'tiktok' => '~\A/@[a-zA-Z0-9._]{1,100}/(video|photo)/([0-9]{1,30})\z~',
            'instagram' => '~\A/(p|reel|reels|tv)/([a-zA-Z0-9_-]{1,50})\z~',
            'facebook' => '~\A/(reel|[a-zA-Z0-9._]+/(?:posts|videos))/([a-zA-Z0-9_]{1,60})\z~',
        };
        if (preg_match($patterns, $path, $match)) {
            if ($platform === 'instagram' && $match[1] === 'reels') {
                $path = '/reel/'.$match[2];
            }

            return ['url' => 'https://'.$host.$path.'/', 'key' => $match[2]];
        }

        if ($platform === 'instagram' && preg_match('~\A/stories/[a-zA-Z0-9._]{1,100}/([0-9]{1,30})\z~', $path, $match)) {
            return ['url' => 'https://'.$host.$path.'/', 'key' => $match[1]];
        }

        if ($platform === 'facebook' && in_array($path, ['/watch', '/photo', '/photo.php', '/story.php', '/permalink.php'], true)) {
            $parameter = match ($path) {
                '/watch' => 'v',
                '/photo', '/photo.php' => 'fbid',
                default => 'story_fbid',
            };
            $id = self::queryValue($query, $parameter);
            $owner = $parameter === 'story_fbid' ? self::queryValue($query, 'id') : null;
            if ($id !== null && ($parameter !== 'story_fbid' || $owner !== null)) {
                $canonicalPath = match ($parameter) {
                    'v' => '/watch/',
                    'fbid' => '/photo/',
                    default => '/permalink.php',
                };

                return [
                    'url' => 'https://'.$host.$canonicalPath.'?'.$parameter.'='.$id.($owner === null ? '' : '&id='.$owner),
                    'key' => $id,
                ];
            }
        }

        self::invalid('url', 'Gunakan tautan permanen unggahan yang didukung.');
    }

    public static function profileUrl(string $platform, string $url): string
    {
        [$host, $path, $query] = self::parseUrl($platform, $url);
        if ($platform === 'facebook' && $path === '/profile.php' && ($id = self::queryValue($query, 'id')) !== null) {
            return 'https://'.$host.$path.'?id='.$id;
        }
        $pattern = $platform === 'tiktok' ? '~\A/@[a-zA-Z0-9._]{1,100}\z~' : '~\A/[a-zA-Z0-9._]{1,100}\z~';
        if (! preg_match($pattern, $path) || in_array(strtolower(ltrim($path, '/')), ['.', '..', 'share', 'watch', 'reel', 'reels', 'login', 'accounts', 'explore', 'stories', 'profile.php'], true)) {
            self::invalid('url', 'Gunakan tautan profil resmi.');
        }

        return 'https://'.$host.$path.'/';
    }

    private static function parseUrl(string $platform, string $url): array
    {
        $url = trim($url);
        $parts = parse_url($url);
        if (strlen($url) > 500 || preg_match('/[\x00-\x20\x7f\\\\]/', $url)
            || strtolower($parts['scheme'] ?? '') !== 'https'
            || ! in_array(strtolower($parts['host'] ?? ''), self::HOSTS[self::platform($platform)], true)
            || array_intersect(['user', 'pass', 'port'], array_keys($parts ?: []))) {
            self::invalid('url', 'Tautan HTTPS harus berasal dari platform yang dipilih.');
        }

        return [self::HOSTS[$platform][0], rtrim($parts['path'] ?? '', '/'), $parts['query'] ?? ''];
    }

    private static function queryValue(string $query, string $key): ?string
    {
        preg_match_all('/(?:\A|&)'.preg_quote($key, '/').'=(?:([a-zA-Z0-9_]{1,60})(?=&|$)|[^&]*)/', $query, $matches);
        $value = $matches[1][0] ?? '';

        return count($matches[0]) === 1 && $value !== '' ? $value : null;
    }

    public static function metrics(array $input, array $fields): array
    {
        if (array_diff(array_keys($input), $fields)) {
            self::invalid('metrics', 'Metrik tidak sesuai dengan cakupan observasi.');
        }

        $metrics = [];
        foreach ($fields as $field) {
            $value = $input[$field] ?? null;
            if ($value === null || $value === '') {
                $metrics[$field] = null;

                continue;
            }
            if ((! is_int($value) && ! is_string($value)) || ! preg_match('/\A(?:0|[1-9][0-9]*)\z/', (string) $value)
                || (string) (int) $value !== (string) $value) {
                self::invalid($field, 'Metrik harus berupa bilangan bulat nonnegatif dalam batas yang didukung.');
            }
            $metrics[$field] = (int) $value;
        }

        return $metrics;
    }

    public static function period(string $month): CarbonImmutable
    {
        if (! preg_match('/\A[1-9][0-9]{3}-(?:0[1-9]|1[0-2])\z/', $month) || $month === '9999-12') {
            self::invalid('period', 'Periode harus berformat YYYY-MM.');
        }

        return CarbonImmutable::createFromFormat('!Y-m', $month, 'Asia/Jakarta');
    }

    public static function version(int $current, int $expected): void
    {
        if ($current !== $expected) {
            throw new TVClubSocialException('SOCIAL_STALE');
        }
    }

    public static function revision(int $current, int $expected, ?string $reason): array
    {
        self::version($current, $expected);
        if ($current >= 4294967295) {
            throw new TVClubSocialException('SOCIAL_STATE');
        }
        $reason = self::label($reason, 'reason', 1000, $current > 0);
        if ($current === 0 && $reason !== null) {
            self::invalid('reason', 'Alasan revisi tersedia setelah versi pertama terbit.');
        }

        return [$current + 1, $reason];
    }

    public static function correction(?int $id, ?string $reason): ?string
    {
        $reason = self::label($reason, 'reason', 1000, $id !== null);
        if (($id !== null && $id < 1) || ($id === null && $reason !== null)) {
            self::invalid('reason', 'Alasan koreksi memerlukan referensi observasi yang valid.');
        }

        return $reason;
    }

    public static function label(?string $value, string $field, int $max, bool $required = true): ?string
    {
        $value = trim($value ?? '');
        if (($required && $value === '') || ! mb_check_encoding($value, 'UTF-8') || mb_strlen($value) > $max) {
            self::invalid($field, 'Periksa isi dan panjang kolom.');
        }

        return $value === '' ? null : $value;
    }

    public static function invalid(string $field, string $message): never
    {
        $validator = new Validator(new Translator(new ArrayLoader, 'id'), [], []);
        $validator->errors()->add($field, $message);

        throw new ValidationException($validator);
    }
}
