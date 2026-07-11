<?php

declare(strict_types=1);

namespace Kopling\AuthEmailPassword\Listeners;

use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Kopling\Core\Authentication\Event\AttemptLogin;
use Kopling\Core\People\Person;

class AttemptPasswordLogin
{
    public function __invoke(AttemptLogin $event): void
    {
        $person = Person::where('email', $event->request->input('email'))->first();

        if ($person && Hash::check((string) $event->request->input('password'), $person->password)) {
            $event->succeeded($person);

            return;
        }

        $event->failed(ValidationException::withMessages([
            'email' => trans('auth.failed'),
        ]));
    }
}
