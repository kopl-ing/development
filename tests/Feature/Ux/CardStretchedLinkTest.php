<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Kopling\Core\Extension\Manager;
use Kopling\Core\Ux\Context;
use Tests\Fixtures\Extensions\ModelExtender\Gadget;

/*
 * `Card`'s whole-card stretched-link overlay, its aura-glow wrapper, trailing caret icon, and
 * `group` class are all gated on the exact same `Context::getSubjectUrl()` lookup
 * `ContextGetSubjectUrlTest.php` already exercises directly -- these confirm `Card` actually
 * wires that resolved value into its own rendered markup, reusing the same `fakeManager()` +
 * `ModelLinker` fixture pattern rather than introducing a second notion of "is this card
 * clickable."
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
        ->not->toContain('class="absolute inset-0 z-0"')
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
        ->toContain('class="absolute inset-0 z-0"')
        ->toContain('aura aura-glow')
        ->toContain('pointer-events-none')
        ->toContain('group cursor-pointer');
});
