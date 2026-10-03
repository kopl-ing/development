<?php

declare(strict_types=1);

namespace Kopling\AuthEmailPassword\Controllers;

use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Kopling\AuthEmailPassword\VerifyEmailNotification;
use Kopling\Core\People\Person;

class VerificationController
{
    public function notice(): View
    {
        return view('kopling-auth-email-password::verify-email');
    }

    /**
     * Answers the same whether or not the address has an unverified account.
     */
    public function resend(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'string', 'email', 'max:255']]);

        Person::where('email', $data['email'])->whereNotNull('password')->whereNull('email_verified_at')->first()
            ?->notify(new VerifyEmailNotification());

        return redirect()->route('kopling-core::community/verification.notice')
            ->with('status', __('kopling-auth-email-password::messages.verification_sent'))
            ->with('verification_email', $data['email']);
    }

    /**
     * Doesn't sign the person in: the link could reach someone else's inbox, so it only proves the address.
     */
    public function verify(string $id, string $hash): RedirectResponse
    {
        $person = Person::find($id);
        abort_unless($person !== null && $person->email !== null && hash_equals(sha1($person->email), $hash), 403);

        if ($person->email_verified_at === null) {
            $person->forceFill(['email_verified_at' => now()])->save();
            event(new Verified($person));
        }

        return redirect()->route('kopling-core::community/login')->with('status', __('kopling-auth-email-password::messages.email_verified'));
    }
}
