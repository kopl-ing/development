<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Kopling\Core\People\Group;
use Kopling\Core\People\Person;

pest()->extend(Tests\TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/**
 * A person with no groups and therefore no grants.
 */
function makePerson(): Person
{
    return Person::create([
        'name' => 'Test Person',
        'email' => uniqid('person', true).'@example.test',
        'password' => 'secret',
    ]);
}

/**
 * Grants `$permission` to `$person` the only way a grant exists: through a group
 * (see Person::hasPermission -- there is no per-person grant).
 */
function grantThroughGroup(Person $person, string $permission): void
{
    $group = Group::create(['name' => uniqid('Testers', true)]);
    $group->people()->attach($person->id);
    $group->givePermissionTo($permission);
}
