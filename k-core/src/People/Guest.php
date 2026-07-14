<?php

declare(strict_types=1);

namespace Kopling\Core\People;

/**
 * The unauthenticated visitor, as a real (never persisted) `Person`. Substituted wherever a
 * Gate ability check would otherwise receive `null` -- see `ServiceProvider::boot()` -- so a
 * permission check never has to special-case "no one's signed in" beyond checking
 * `instanceof Guest`, and everything else about `Person` (relations, `hasPermission()`'s
 * signature) stays exactly one shape.
 *
 * `hasPermission()` always returns `false`: a guest is never persisted, so it can never hold a
 * real Group grant (there's no row for `group_person` to reference). The only way a permission
 * ever applies to a guest is `Extend\Permission::$allowsGuests`, checked separately in the Gate
 * closure -- this method deliberately doesn't know about that, so a stray direct call to
 * `hasPermission()` never accidentally grants something a permission didn't opt into.
 */
class Guest extends Person
{
    public function hasPermission(string $id): bool
    {
        return false;
    }
}
