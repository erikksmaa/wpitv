<?php

namespace Tests\Browser;

final class ReleaseQaSession
{
    public function handle($request, $next)
    {
        $request->session()->put(['auth_started_at' => time(), 'auth_last_activity_at' => time(), 'auth.password_confirmed_at' => time(), 'password_hash_web' => auth()->user()->getAuthPassword()]);

        return $next($request);
    }
}
