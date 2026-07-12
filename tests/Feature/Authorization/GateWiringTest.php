<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Kopling\Core\People\Group;
use Kopling\Core\People\Person;

/*
 * Exercises ServiceProvider::boot()'s Gate::define() closure against real, currently-installed
 * permissions -- kopling-example::manage-things (no default, base grant only) and
 * kopling-discussions::view (default: true). Not covered: Permission::$callback -- no installed
 * extension uses it today, so there's nothing real to exercise it against; see
 * tests/Unit/Extension/ManagerPermissionTest.php for the aggregation/prefixing side instead.
 */

it('denies a permission with no default until a group grants it, then allows it', function () {
    $person = Person::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'secret']);

    expect(Gate::forUser($person)->allows('kopling-example::manage-things'))->toBeFalse();

    $group = Group::create(['name' => 'Widget Managers']);
    $group->givePermissionTo('kopling-example::manage-things');
    $person->groups()->attach($group);

    expect(Gate::forUser($person)->allows('kopling-example::manage-things'))->toBeTrue();
});

it('allows a permission declared with default: true even with zero groups', function () {
    $person = Person::create(['name' => 'Grace', 'email' => 'grace@example.test', 'password' => 'secret']);

    expect(Gate::forUser($person)->allows('kopling-discussions::view'))->toBeTrue();
});
