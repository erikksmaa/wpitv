<?php

namespace Tests\Feature;

use App\Http\Middleware\AddSecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class ProductionSecurityTest extends TestCase
{
    public function test_public_responses_include_security_headers_and_a_restrictive_report_only_csp(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), browsing-topics=()')
            ->assertHeaderMissing('X-Powered-By')
            ->assertHeaderMissing('Strict-Transport-Security')
            ->assertHeaderMissing('Content-Security-Policy');

        $policy = $response->headers->get('Content-Security-Policy-Report-Only');
        $this->assertNotNull($policy);
        foreach ([
            "default-src 'self'",
            "object-src 'none'",
            "frame-ancestors 'self'",
            'https://fonts.googleapis.com',
            'https://fonts.gstatic.com',
            'https://www.youtube.com',
            'https://img.youtube.com',
            'upgrade-insecure-requests',
        ] as $directive) {
            $this->assertStringContainsString($directive, $policy);
        }
        foreach (["'unsafe-inline'", "'unsafe-eval'", 'data:', '*'] as $unsafeSource) {
            $this->assertStringNotContainsString($unsafeSource, $policy);
        }
    }

    public function test_hsts_is_only_added_to_secure_production_responses(): void
    {
        $application = $this->app;
        $originalEnvironment = $application['env'];
        $application['env'] = 'production';

        try {
            $middleware = new AddSecurityHeaders($application);
            $secure = $middleware->handle(
                Request::create('https://example.test/', server: ['HTTPS' => 'on', 'SERVER_PORT' => 443]),
                fn (): Response => new Response('ok'),
            );
            $insecure = $middleware->handle(
                Request::create('http://example.test/'),
                fn (): Response => new Response('ok'),
            );

            $this->assertSame('max-age=31536000; includeSubDomains', $secure->headers->get('Strict-Transport-Security'));
            $this->assertFalse($insecure->headers->has('Strict-Transport-Security'));
        } finally {
            $application['env'] = $originalEnvironment;
        }
    }

    public function test_production_template_has_secure_non_root_defaults(): void
    {
        $environment = file_get_contents(base_path('.env.example'));

        foreach ([
            'APP_ENV=production',
            'APP_DEBUG=false',
            'APP_URL=https://YOUR-WPITV-DOMAIN',
            'DB_USERNAME=wpitv_app',
            'DB_PASSWORD=CHANGE_ME_WITH_A_STRONG_UNIQUE_PASSWORD',
            'SESSION_SECURE_COOKIE=true',
            'SESSION_HTTP_ONLY=true',
            'SESSION_SAME_SITE=lax',
            'TRUSTED_HOSTS=YOUR-WPITV-DOMAIN',
            'SECURITY_CSP_REPORT_ONLY=true',
        ] as $setting) {
            $this->assertStringContainsString($setting, $environment);
        }
        $this->assertStringNotContainsString('DB_USERNAME=root', $environment);
    }

    public function test_trust_storage_and_route_boundaries_remain_explicit(): void
    {
        $this->assertSame(['^localhost$'], config('security.trusted_hosts'));
        $this->assertNull(config('trustedproxy.proxies'));
        $this->assertStringNotContainsString(public_path(), config('filesystems.disks.local.root'));
        $this->assertSame('local', config('livewire.temporary_file_upload.disk'));
        $this->assertFileDoesNotExist(public_path('hot'));

        $routes = Route::getRoutes();
        foreach ($routes as $route) {
            $identity = strtolower(($route->getName() ?? '').' '.$route->uri());
            foreach (['register', 'forgot-password', 'reset-password', 'email/verify', 'banner', '_debugbar', 'test-only'] as $forbidden) {
                $this->assertStringNotContainsString($forbidden, $identity);
            }
        }

        $webMiddleware = $this->app->make(Application::class)->make('router')->getMiddlewareGroups()['web'];
        $this->assertContains(ValidateCsrfToken::class, $webMiddleware);
    }
}
