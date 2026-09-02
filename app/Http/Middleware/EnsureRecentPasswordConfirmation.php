<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Symfony\Component\HttpFoundation\Response;

class EnsureRecentPasswordConfirmation
{
    public function handle(Request $request, Closure $next): Response
    {
        $confirmedAt = (int) $request->session()->get('auth.password_confirmed_at', 0);
        if ((Date::now()->unix() - $confirmedAt) > config('auth.password_timeout')) {
            // A redirect is intentionally used for Livewire too: its persistent middleware
            // pipeline aborts on redirects, preventing the stale component call.
            return redirect()->guest(route('password.confirm'));
        }

        return $next($request);
    }
}
