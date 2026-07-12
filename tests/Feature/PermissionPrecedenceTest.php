<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Kopling\Core\Authorization\Permission;

/**
 * Permission::authorize's precedence contract (see the Permission class docblock): the base
 * grant first -- held via one of the person's groups, else the declared default -- then the
 * callback, when present, strictly as a further condition on top. Never the reverse: a group
 * grant must not skip the callback, and the callback must not grant without a base grant.
 */
function permission(?bool $default = null, ?\Closure $callback = null): Permission
{
    return new Permission('test::do-a-thing', 'Do a thing', 'A test capability.', $default, $callback);
}

it('grants through a group', function () {
    $person = makePerson();
    grantThroughGroup($person, 'test::do-a-thing');

    expect(permission()->authorize($person))->toBeTrue();
});

it('denies without a grant or a default', function () {
    expect(permission()->authorize(makePerson()))->toBeFalse();
});

it('grants through the declared default, for members and guests alike', function () {
    expect(permission(default: true)->authorize(makePerson()))->toBeTrue()
        ->and(permission(default: true)->authorize(null))->toBeTrue();
});

it('lets the callback narrow a group grant down to nothing', function () {
    $person = makePerson();
    grantThroughGroup($person, 'test::do-a-thing');

    expect(permission(callback: fn () => false)->authorize($person))->toBeFalse();
});

it('keeps a group grant the callback agrees with', function () {
    $person = makePerson();
    grantThroughGroup($person, 'test::do-a-thing');

    expect(permission(callback: fn () => true)->authorize($person))->toBeTrue();
});

it('never lets the callback grant on its own', function () {
    expect(permission(callback: fn () => true)->authorize(makePerson()))->toBeFalse();
});

it('layers the callback on top of a default grant too, including for guests', function () {
    expect(permission(default: true, callback: fn () => false)->authorize(null))->toBeFalse()
        ->and(permission(default: true, callback: fn ($person) => $person === null)->authorize(null))->toBeTrue();
});

it('hands the callback whatever the check passed along', function () {
    $person = makePerson();
    grantThroughGroup($person, 'test::do-a-thing');

    $subject = new stdClass();
    $seen = null;

    permission(callback: function ($actor, $received) use (&$seen) {
        $seen = $received;

        return true;
    })->authorize($person, $subject);

    expect($seen)->toBe($subject);
});

it('registers every declared permission with the gate, guests included', function () {
    // kopling-discussions::view ships default: true and no callback -- the wiring in
    // ServiceProvider::boot must let a guest through and an undeclared id stay blocked.
    expect(Gate::allows('kopling-discussions::view'))->toBeTrue()
        ->and(Gate::forUser(makePerson())->allows('kopling-discussions::view'))->toBeTrue()
        ->and(Gate::allows('kopling-discussions::never-declared'))->toBeFalse();
});
