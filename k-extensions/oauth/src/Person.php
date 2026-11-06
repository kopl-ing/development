<?php

declare(strict_types=1);

namespace Kopling\OAuth;

use Kopling\Core\People\Person as CorePerson;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;

/**
 * Core's `Person` with Passport's token methods, used only by the `api` guard's provider, so core never depends on Passport.
 */
class Person extends CorePerson implements OAuthenticatable
{
    use HasApiTokens;

    public function getMorphClass(): string
    {
        return (new CorePerson())->getMorphClass();
    }

    /** Passport only recognises `eloquent` providers by itself; this one adds the sanction check (see `PeopleProvider`). */
    public function getProviderName(): string
    {
        return ServiceProvider::PROVIDER;
    }
}
