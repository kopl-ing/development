<?php

declare(strict_types=1);

namespace Kopling\AuthEmailPassword\Listeners;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Kopling\Core\Authentication\Event\AttemptRegistration;
use Kopling\Core\People\Person;

class AttemptPasswordRegistration
{
    public function __invoke(AttemptRegistration $event): void
    {
        $validator = Validator::make($event->request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:'.Person::class],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if ($validator->fails()) {
            $event->failed(ValidationException::withMessages($validator->errors()->toArray()));

            return;
        }

        $event->succeeded(new Person($validator->validated()));
    }
}
