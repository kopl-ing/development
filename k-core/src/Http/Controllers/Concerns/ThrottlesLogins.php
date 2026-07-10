<?php

declare(strict_types=1);

namespace Kopling\Core\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Same shape as laravel/ui's own ThrottlesLogins trait -- not installed here (see
 * LoginController's own docblock), so hand-written rather than `use`d from that package.
 */
trait ThrottlesLogins
{
    protected function hasTooManyLoginAttempts(Request $request): bool
    {
        return RateLimiter::tooManyAttempts(
            $this->throttleKey($request),
            $this->maxAttempts(),
        );
    }

    protected function incrementLoginAttempts(Request $request): void
    {
        RateLimiter::hit(
            $this->throttleKey($request),
            $this->decayMinutes() * 60,
        );
    }

    protected function sendLockoutResponse(Request $request): never
    {
        $seconds = RateLimiter::availableIn($this->throttleKey($request));

        throw ValidationException::withMessages([
            trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => (int) ceil($seconds / 60),
            ]),
        ])->status(429);
    }

    protected function clearLoginAttempts(Request $request): void
    {
        RateLimiter::clear($this->throttleKey($request));
    }

    protected function throttleKey(Request $request): string
    {
        return Str::transliterate($request->ip());
    }

    protected function maxAttempts(): int
    {
        return 5;
    }

    protected function decayMinutes(): int
    {
        return 1;
    }
}
