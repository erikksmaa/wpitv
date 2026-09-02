<?php

$applicationHost = parse_url((string) env('APP_URL', ''), PHP_URL_HOST);
$configuredHosts = array_filter(array_map(
    static fn (string $host): string => trim($host),
    explode(',', (string) env('TRUSTED_HOSTS', $applicationHost ?: '')),
));

return [
    'trusted_hosts' => array_map(
        static fn (string $host): string => '^'.preg_quote($host, '/').'$',
        $configuredHosts,
    ),

    'csp_report_only' => env('SECURITY_CSP_REPORT_ONLY', true),

    'content_security_policy' => implode('; ', [
        "default-src 'self'",
        "base-uri 'self'",
        "object-src 'none'",
        "frame-ancestors 'self'",
        "form-action 'self'",
        "script-src 'self'",
        "style-src 'self' https://fonts.googleapis.com",
        "font-src 'self' https://fonts.gstatic.com",
        "img-src 'self' https://img.youtube.com",
        'frame-src https://www.youtube.com https://www.youtube-nocookie.com',
        "connect-src 'self'",
        "media-src 'self'",
        "worker-src 'self' blob:",
        'upgrade-insecure-requests',
    ]),
];
