<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Kopling\Core\Content\Moment;
use Kopling\Core\Extension\Manager;
use Kopling\Core\People\Person;
use Kopling\Core\Ux\Context;
use Tests\Fixtures\Extensions\ModelExtender\Gadget;

/*
 * `Card`'s whole-card `data-href` click target, its aura-glow wrapper, trailing caret icon, and
 * `group` class are all gated on the exact same `Context::getSubjectUrl()` lookup
 * `ContextGetSubjectUrlTest.php` already exercises directly -- these confirm `Card` actually
 * wires that resolved value into its own rendered markup, reusing the same `fakeManager()` +
 * `ModelLinker` fixture pattern rather than introducing a second notion of "is this card
 * clickable." The real link lives on `Card\Title`'s own `<a>` -- `data-href` is only the
 * generic delegated-click enhancement (`app.js`) on top of that.
 */

beforeEach(function () {
    Schema::create('fixture_gadgets', function ($table) {
        $table->id();
        $table->text('metadata')->nullable();
    });

    Route::get('/fixture-gadgets/{gadget}', fn () => '')->name('fixture-gadgets.show');
    app('router')->getRoutes()->refreshNameLookups();
});

it('renders no overlay, aura wrapper, caret icon, or group class when the subject has no linksTo() registration', function () {
    app()->instance(Manager::class, fakeManager([]));

    $gadget = Gadget::create();

    $html = (string) $this->blade('<x-k::card.card :context="$context" />', [
        'context' => new Context(subject: $gadget),
    ]);

    expect($html)
        ->not->toContain('data-href')
        ->not->toContain('aura aura-glow')
        ->not->toContain('group cursor-pointer');
});

it('renders a stretched-link overlay, aura-glow wrapper, caret icon, and group class when the subject has a linksTo() registration', function () {
    app()->instance(Manager::class, fakeManager([
        'tests-fixtures/model-linker' => [
            'namespace' => 'Tests\\Fixtures\\Extensions\\ModelLinker\\',
            'path' => __DIR__,
        ],
    ]));

    $gadget = Gadget::create();

    $html = (string) $this->blade('<x-k::card.card :context="$context" />', [
        'context' => new Context(subject: $gadget),
    ]);

    expect($html)
        ->toContain('href="'.route('fixture-gadgets.show', $gadget->id).'"')
        ->toContain('data-href="'.route('fixture-gadgets.show', $gadget->id).'"')
        ->toContain('aura aura-glow')
        ->toContain('group cursor-pointer');
});

it('wires the real linksTo() case (a Moment linking to its discussions page) with the hx-boosted primary link', function () {
    $author = Person::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'secret']);
    $moment = Moment::create(['person_id' => $author->id, 'title' => 'Real moment', 'body' => 'Body']);

    $html = (string) $this->blade('<x-k::card.card :context="$context" />', [
        'context' => new Context(subject: $moment),
    ]);

    $url = route('kopling-core::community/discussions.show', $moment);

    expect($html)
        ->toContain('data-href="'.$url.'"')
        ->and($html)->toContain('data-card-primary-link')
        ->and($html)->toContain('hx-boost="true"')
        ->and($html)->toContain('hx-target="#main-content"')
        ->and($html)->toContain('aura aura-glow')
        ->and($html)->toContain('group cursor-pointer');
});
