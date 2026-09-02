<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        // Re-read authority for this request, including persistent Livewire updates.
        // Never retain a role relation from a previous request or an old snapshot.
        $currentUser = User::with('role')->find(Auth::id());

        if (! $currentUser || ! $currentUser->status) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'username' => 'Akun Anda dinonaktifkan.',
            ]);
        }

        Auth::guard('web')->setUser($currentUser);

        return $next($request);
    }
}
