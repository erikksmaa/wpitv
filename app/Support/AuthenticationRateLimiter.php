<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthenticationRateLimiter
{
    public const MAX_ATTEMPTS = 5;

    public const DECAY_SECONDS = 60;

    public function ensureNotLimited(
        Request $request,
        string $action,
        string $errorBag = 'default',
        string $field = 'password'
    ): void {
        $key = $this->key($request, $action);
        if (! RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return;
        }

        $seconds = RateLimiter::availableIn($key);
        $exception = ValidationException::withMessages([
            $field => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
        $exception->errorBag = $errorBag;

        throw $exception;
    }

    public function hit(Request $request, string $action): void
    {
        RateLimiter::hit($this->key($request, $action), self::DECAY_SECONDS);
    }

    public function clear(Request $request, string $action): void
    {
        RateLimiter::clear($this->key($request, $action));
    }

    public function key(Request $request, string $action): string
    {
        return 'auth:'.hash('sha256', $action.'|'.$request->user()->getAuthIdentifier().'|'.$request->ip());
    }
}
