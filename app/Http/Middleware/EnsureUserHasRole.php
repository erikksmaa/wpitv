<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();

        if (! $user->role) {
            abort(403, 'User does not have any assigned role.');
        }

        // If Super Admin, allow access or check if role matches
        if ($user->role->name === 'Super Admin') {
            return $next($request);
        }

        if (in_array($user->role->name, $roles)) {
            return $next($request);
        }

        abort(403, 'Unauthorized action.');
    }
}
