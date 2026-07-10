<?php

declare(strict_types=1);

namespace Kopling\Core\Http\Controllers;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Kopling\Core\Authentication\Event\AttemptLogin;
use Kopling\Core\Authentication\Event\ValidateLogin;
use Kopling\Core\Http\Controllers\Concerns\RedirectsUsers;
use Kopling\Core\Http\Controllers\Concerns\ThrottlesLogins;
use Kopling\Core\People\Person;

/**
 * Same method shape as laravel/ui's AuthenticatesUsers trait (credentials()/attemptLogin()/
 * sendLoginResponse()/etc.) -- that package isn't installed here (its traits were split out
 * of core Laravel in 6.0), so this is hand-written directly on the controller rather than
 * composed from it. Deliberately the full classic scaffold for now, not Kopling's own final
 * shape: actually handling the POST here is a placeholder. Once a real login-method extension
 * (e.g. `kopling/auth-password`) exists, this is the seam something like a captcha check would
 * need to hook into -- not solved yet. Trim down once that's settled.
 */
class LoginController
{
    use RedirectsUsers;
    use ThrottlesLogins;

    protected string $redirectTo = '/';

    public function __construct(protected Dispatcher $events)
    {}

    public function showLoginForm(): View
    {
        return view('core::auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $this->validateLogin($request);

        if ($this->hasTooManyLoginAttempts($request)) {
            $this->sendLockoutResponse($request);
        }

        $event = $this->attemptLogin($request);

        if ($event->person) {
            Auth::login($event->person);

            return $this->sendLoginResponse($request);
        }

        $this->incrementLoginAttempts($request);

        throw $event->e;
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    protected function validateLogin(Request $request): void
    {
        $this->events->until(new ValidateLogin($request));
    }

    protected function attemptLogin(Request $request): AttemptLogin
    {
        $event = new AttemptLogin($request);

        $this->events->dispatch($event);

        return $event;
    }

    protected function sendLoginResponse(Request $request): RedirectResponse
    {
        $request->session()->regenerate();

        $this->clearLoginAttempts($request);

        return redirect()->intended($this->redirectTo());
    }
}
