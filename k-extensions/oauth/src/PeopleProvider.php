<?php

declare(strict_types=1);

namespace Kopling\OAuth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The token guard's provider: a person whose access is blocked by a sanction resolves to no one, like the web guard's `EnforceSanctions`.
 */
class PeopleProvider extends EloquentUserProvider
{
    public function retrieveById($identifier): ?Authenticatable
    {
        $person = parent::retrieveById($identifier);

        return $person instanceof Person && $person->isAccessBlocked() ? null : $person;
    }
}
