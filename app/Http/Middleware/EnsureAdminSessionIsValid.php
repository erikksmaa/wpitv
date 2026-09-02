<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminSessionIsValid
{
    public const STARTED_AT = 'auth_started_at';

    public const LAST_ACTIVITY_AT = 'auth_last_activity_at';

    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $startedAt = filter_var($request->session()->get(self::STARTED_AT), FILTER_VALIDATE_INT);
        $lastActivityAt = filter_var($request->session()->get(self::LAST_ACTIVITY_AT), FILTER_VALIDATE_INT);
        if ($startedAt === false || $lastActivityAt === false) {
            return $this->logoutAndRedirect($request, 'Sesi Anda telah berakhir. Silakan masuk kembali.');
        }

        $now = now()->timestamp;
        if ($startedAt > $now || $lastActivityAt > $now || $lastActivityAt < $startedAt) {
            return $this->logoutAndRedirect($request, 'Sesi Anda telah berakhir. Silakan masuk kembali.');
        }

        $idleSeconds = config('admin_session.idle_timeout_minutes') * 60;
        $absoluteSeconds = config('admin_session.absolute_lifetime_hours') * 3600;

        if (($now - $lastActivityAt) > $idleSeconds) {
            return $this->logoutAndRedirect(
                $request,
                'Sesi Anda berakhir karena tidak ada aktivitas selama 30 menit. Silakan masuk kembali.'
            );
        }

        if (($now - $startedAt) >= $absoluteSeconds) {
            return $this->logoutAndRedirect($request, 'Sesi Anda telah berakhir. Silakan masuk kembali.');
        }

        $response = $next($request);

        // Only a completed, authorized request counts as authenticated activity.
        if (Auth::check() && $response->getStatusCode() < 400) {
            $request->session()->put(self::LAST_ACTIVITY_AT, now()->timestamp);
        }

        return $response;
    }

    private function logoutAndRedirect(Request $request, string $message): RedirectResponse
    {
        Auth::guard('web')->logoutCurrentDevice();
        $request->session()->forget([self::STARTED_AT, self::LAST_ACTIVITY_AT]);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors(['username' => $message]);
    }
}
