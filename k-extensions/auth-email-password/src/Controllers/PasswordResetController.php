<?php

declare(strict_types=1);

namespace Kopling\AuthEmailPassword\Controllers;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Kopling\AuthEmailPassword\ResetPasswordNotification;
use Kopling\Core\People\Person;

class PasswordResetController
{
    public function request(): View
    {
        return view('kopling-auth-email-password::forgot-password');
    }

    /**
     * Answers the same whether or not the address has an account, so the form can't be used to find out.
     */
    public function email(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'string', 'email', 'max:255']]);

        Password::broker()->sendResetLink(
            ['email' => $data['email']],
            fn (Person $person, string $token) => $person->notify(new ResetPasswordNotification($token)),
        );

        return back()->with('status', __('kopling-auth-email-password::messages.reset_link_sent'));
    }

    public function edit(Request $request, string $token): View
    {
        return view('kopling-auth-email-password::reset-password', ['token' => $token, 'email' => (string) $request->query('email')]);
    }

    public function update(Request $request, string $token): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::broker()->reset(
            [...$data, 'password_confirmation' => $request->input('password_confirmation'), 'token' => $token],
            function (Person $person, string $password) {
                $person->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                    'email_verified_at' => $person->email_verified_at ?? now(),
                ])->save();
                event(new PasswordReset($person));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __('kopling-auth-email-password::messages.reset_failed')]);
        }

        return redirect()->route('kopling-core::community/login')->with('status', __('kopling-auth-email-password::messages.password_reset'));
    }
}
