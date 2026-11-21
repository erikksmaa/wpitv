<?php

namespace App\Support;

final class YouTubeVideoId
{
    public static function isValid(?string $id): bool
    {
        return $id !== null && preg_match('/\A[A-Za-z0-9_-]{11}\z/', $id) === 1;
    }

    public static function parse(string $input): ?string
    {
        if (strlen($input) > 2048 || preg_match('/[\x00-\x1F\x7F]/', $input)) {
            return null;
        }
        $input = trim($input);
        if (self::isValid($input)) {
            return $input;
        }
        if (preg_match('/[\s\\\\]/', $input)) {
            return null;
        }
        $url = parse_url($input);
        if (! is_array($url) || ($url['scheme'] ?? '') !== 'https'
            || isset($url['user']) || isset($url['pass']) || isset($url['port'])
            || ! in_array(strtolower($url['host'] ?? ''), ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtu.be'], true)) {
            return null;
        }

        $videoParameters = [];
        foreach (explode('&', $url['query'] ?? '') as $pair) {
            [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $key = urldecode($key);
            if (str_starts_with($key, 'v[') || str_contains($pair, ';')) {
                return null;
            }
            if ($key === 'v') {
                $videoParameters[] = urldecode($value);
            }
        }
        $path = $url['path'] ?? '';
        if (strtolower($url['host']) === 'youtu.be') {
            return $videoParameters === [] && preg_match('~/([A-Za-z0-9_-]{11})\z~A', $path, $match)
                ? $match[1] : null;
        }
        if ($path === '/watch') {
            return count($videoParameters) === 1 && self::isValid($videoParameters[0]) ? $videoParameters[0] : null;
        }

        return $videoParameters === [] && preg_match('~\A/(?:live|embed)/([A-Za-z0-9_-]{11})\z~', $path, $match)
            ? $match[1] : null;
    }

    public static function watchUrl(?string $id): ?string
    {
        return self::isValid($id) ? 'https://www.youtube.com/watch?v='.$id : null;
    }

    public static function embedUrl(?string $id): ?string
    {
        return self::isValid($id) ? 'https://www.youtube-nocookie.com/embed/'.$id.'?playsinline=1&autoplay=0' : null;
    }
}
