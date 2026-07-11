<?php

declare(strict_types=1);

namespace Kopling\Core\Authentication\Controller;

use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Kopling\Core\Authentication\Event\AttemptRegistration;
use Kopling\Core\Authentication\Event\ValidateRegistration;
use Kopling\Core\Http\Controllers\Concerns\RedirectsUsers;

/**
 * Same `Validate*`/`Attempt*` event-pair shape as `LoginController`'s `ValidateLogin`/
 * `AttemptLogin`, mirrored here as `ValidateRegistration`/`AttemptRegistration`: Core has no
 * opinion on what "signing up" means, same as it has none on what "credentials" means for
 * login -- that's a registration-capable extension's job (`kopling/auth-email-password` is the
 * first one). See `AttemptRegistration`'s own docblock for why `$event->person` may still be
 * unsaved by the time `register()` reads it back.
 */
class RegistrationController
{
    use RedirectsUsers;

    protected string $redirectTo = '/';

    public function __construct(protected Dispatcher $events)
    {}

    public function showRegistrationForm(Request $request): View
    {
        return view('kopling-core::auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $this->validateRegistration($request);

        $event = $this->attemptRegistration($request);

        if ($event->person) {
            $event->person->save();

            event(new Registered($event->person));

            Auth::login($event->person);

            return $this->sendRegistrationResponse($request);
        }

        throw $event->e;
    }

    protected function validateRegistration(Request $request): void
    {
        $this->events->until(new ValidateRegistration($request));
    }

    protected function attemptRegistration(Request $request): AttemptRegistration
    {
        $event = new AttemptRegistration($request);

        $this->events->dispatch($event);

        return $event;
    }

    protected function sendRegistrationResponse(Request $request): RedirectResponse
    {
        $request->session()->regenerate();

        return redirect()->intended($this->redirectTo());
    }
}
