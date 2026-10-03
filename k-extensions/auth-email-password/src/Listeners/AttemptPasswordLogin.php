<?php

declare(strict_types=1);

namespace Kopling\AuthEmailPassword\Listeners;

use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Kopling\AuthEmailPassword\Extension;
use Kopling\Core\Authentication\Event\AttemptLogin;
use Kopling\Core\People\Person;

class AttemptPasswordLogin
{
    public function __invoke(AttemptLogin $event): void
    {
        $person = Person::where('email', $event->request->input('email'))->first();

        if ($person && Hash::check((string) $event->request->input('password'), $person->password)) {
            // Only after the password matched, so the message can't reveal which addresses have accounts.
            if (Extension::verificationRequired() && $person->email_verified_at === null) {
                $event->request->session()->flash('verification_email', $person->email);
                $event->failed(ValidationException::withMessages([
                    'email' => __('kopling-auth-email-password::messages.email_unverified'),
                ]));

                return;
            }

            $event->succeeded($person);

            return;
        }

        $event->failed(ValidationException::withMessages([
            'email' => trans('auth.failed'),
        ]));
    }
}
