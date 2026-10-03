<?php

declare(strict_types=1);

namespace Kopling\AuthEmailPassword\Listeners;

use Illuminate\Auth\Events\Registered;
use Kopling\AuthEmailPassword\Extension;
use Kopling\AuthEmailPassword\VerifyEmailNotification;
use Kopling\Core\People\Person;

class SendVerificationEmail
{
    public function __invoke(Registered $event): void
    {
        $person = $event->user;
        if (! Extension::verificationRequired() || ! $person instanceof Person || $person->email === null || $person->email_verified_at !== null) {
            return;
        }

        $person->notify(new VerifyEmailNotification());
        session()->flash('verification_email', $person->email);
    }
}
