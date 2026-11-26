<?php

use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\EnsureAdminSessionIsValid;
use App\Http\Middleware\EnsureRecentPasswordConfirmation;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureUserIsActive;
use App\Support\LiveStreamErrors;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(AddSecurityHeaders::class);
        $middleware->trustHosts(at: fn (): array => config('security.trusted_hosts'), subdomains: false);
        // Native password-hash comparison revokes sessions across drivers after a password change.
        $middleware->authenticateSessions();
        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'role' => EnsureUserHasRole::class,
            'session.valid' => EnsureAdminSessionIsValid::class,
            'password.recent' => EnsureRecentPasswordConfirmation::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Scope sanitization to Live admin paths before reporting
        // and rendering, including debug responses and shared Livewire requests.
        $exceptions->map(function (Throwable $exception): Throwable {
            $request = app()->bound('request') ? request() : null;

            return LiveStreamErrors::forHandler($exception, $request);
        });
    })->create();
