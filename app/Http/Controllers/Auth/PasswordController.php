<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\AuthenticationRateLimiter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(Request $request, AuthenticationRateLimiter $limiter): RedirectResponse
    {
        $limiter->ensureNotLimited($request, 'password-update', 'updatePassword', 'current_password');

        try {
            $validated = $request->validateWithBag('updatePassword', [
                'current_password' => ['required', 'current_password'],
                'password' => ['required', Password::defaults(), 'confirmed'],
            ]);
        } catch (ValidationException $exception) {
            if (array_key_exists('current_password', $exception->errors())) {
                $limiter->hit($request, 'password-update');
            }

            throw $exception;
        }

        $limiter->clear($request, 'password-update');

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('status', 'password-updated');
    }
}
