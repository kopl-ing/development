<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Kopling\Core\People\Guest;
use Kopling\Core\People\Person;
use Kopling\Core\Ux\Context;
use Tests\Fixtures\Extensions\ModelExtender\Gadget;

/*
 * `getRoute()`/`isRoute()` read the request's own matched route, so these register a real,
 * implicitly-bound fixture route (the closure param is type-hinted to `Gadget`, unlike
 * ContextGetSubjectUrlTest.php's own route -- that one never resolves a model, this one must) and
 * actually dispatch it via `$this->get()`, the same route-registration approach
 * ContextGetSubjectUrlTest.php already uses.
 */

beforeEach(function () {
    Schema::create('fixture_gadgets', function ($table) {
        $table->id();
        $table->text('metadata')->nullable();
    });

    // ->middleware('web') -- implicit route-model-binding is resolved by SubstituteBindings,
    // part of the default 'web' group; an ad hoc route registered outside any group (like
    // ContextGetSubjectUrlTest.php's own fixture route) never runs it, leaving {gadget} an
    // unresolved raw string, which isRoute() needs to be a real bound Gadget to compare against.
    Route::get('/fixture-gadgets/{gadget}', fn (Gadget $gadget) => '')
        ->middleware('web')
        ->name('fixture-gadgets.show');
    app('router')->getRoutes()->refreshNameLookups();
});

it('getRoute() is null when the current request was never matched to a route', function () {
    expect((new Context())->getRoute())->toBeNull();
});

it('getRoute() returns the route the current request was matched against', function () {
    $gadget = Gadget::create();

    $this->get(route('fixture-gadgets.show', $gadget->id));

    expect((new Context(subject: $gadget))->getRoute()?->getName())->toBe('fixture-gadgets.show');
});

it('isRoute() is true when the named route parameter is bound to this same subject', function () {
    $gadget = Gadget::create();

    $this->get(route('fixture-gadgets.show', $gadget->id));

    expect((new Context(subject: $gadget))->isRoute('gadget'))->toBeTrue();
});

it('isRoute() is false for a different subject than the one the route parameter is bound to', function () {
    $gadget = Gadget::create();
    $other = Gadget::create();

    $this->get(route('fixture-gadgets.show', $gadget->id));

    expect((new Context(subject: $other))->isRoute('gadget'))->toBeFalse();
});

it('isRoute() is false when the context carries no concrete subject', function () {
    $gadget = Gadget::create();

    $this->get(route('fixture-gadgets.show', $gadget->id));

    expect((new Context())->isRoute('gadget'))->toBeFalse();
});

it('getActor() returns the signed-in Person unchanged', function () {
    $person = Person::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'secret']);

    expect((new Context(actor: $person))->getActor())->toBe($person);
});

it('getActor() substitutes a Guest when nobody is signed in', function () {
    expect((new Context())->getActor())->toBeInstanceOf(Guest::class);
});

it('isActor() is true when the given person is the current actor', function () {
    $person = Person::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'secret']);

    expect((new Context(actor: $person))->isActor($person))->toBeTrue();
});

it('isActor() is false for a different person', function () {
    $person = Person::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'secret']);
    $other = Person::create(['name' => 'Bob', 'email' => 'bob@example.test', 'password' => 'secret']);

    expect((new Context(actor: $person))->isActor($other))->toBeFalse();
});

it('isActor() is false against a Guest actor, even for a real, persisted person', function () {
    $person = Person::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'secret']);

    expect((new Context())->isActor($person))->toBeFalse();
});

it('$portal defaults from the current request\'s InjectPortal attribute, same as $actor defaults from Auth::user()', function () {
    $this->get('/');

    expect((new Context())->isPortal('kopling-core::community'))->toBeTrue();
});

it('isPortal() is false for a different portal id than the one actually resolved', function () {
    $this->get('/');

    expect((new Context())->isPortal('kopling-admin::admin'))->toBeFalse();
});

it('isPortal() is false when the current request was never matched to any portal at all', function () {
    expect((new Context())->isPortal('kopling-core::community'))->toBeFalse();
});
