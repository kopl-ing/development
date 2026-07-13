<?php

declare(strict_types=1);

$adder = ['namespace' => 'Tests\\Fixtures\\Extensions\\UxAdder\\', 'path' => __DIR__];
$replacer = ['namespace' => 'Tests\\Fixtures\\Extensions\\UxReplacer\\', 'path' => __DIR__];
$remover = ['namespace' => 'Tests\\Fixtures\\Extensions\\UxRemover\\', 'path' => __DIR__];

it('adds an entry with its id -- and a string condition -- prefixed by the owning package id', function () use ($adder) {
    $manager = fakeManager(['tests-fixtures/ux-adder' => $adder]);

    $entries = $manager->ux()->keyBy('id');

    expect($entries->has('tests-fixtures-ux-adder::widget'))->toBeTrue()
        ->and($entries->get('tests-fixtures-ux-adder::gadget')->condition)
        ->toBe('tests-fixtures-ux-adder::view-gadget');
});

it('lets a later-processed extension replace an earlier one\'s component/data in place, keeping its position', function () use ($adder, $replacer) {
    $manager = fakeManager([
        'tests-fixtures/ux-adder' => $adder,
        'tests-fixtures/ux-replacer' => $replacer,
    ]);

    $entries = $manager->ux();
    $ids = $entries->pluck('id')->values()->all();

    $widget = $entries->firstWhere('id', 'tests-fixtures-ux-adder::widget');

    expect($widget->component)->toBe('fixture::widget-v2')
        ->and($widget->data)->toBe(['replaced' => true])
        ->and(array_search('tests-fixtures-ux-adder::widget', $ids, true))
        ->toBeLessThan(array_search('tests-fixtures-ux-adder::gadget', $ids, true));
});

it('lets a later-processed extension remove an earlier one\'s entry outright', function () use ($adder, $remover) {
    $manager = fakeManager([
        'tests-fixtures/ux-adder' => $adder,
        'tests-fixtures/ux-remover' => $remover,
    ]);

    $entries = $manager->ux()->keyBy('id');

    expect($entries->has('tests-fixtures-ux-adder::gadget'))->toBeFalse()
        ->and($entries->has('tests-fixtures-ux-adder::widget'))->toBeTrue();
});

it('silently no-ops a replace()/remove() whose target was never registered', function () use ($replacer, $remover) {
    $manager = fakeManager([
        'tests-fixtures/ux-replacer' => $replacer,
        'tests-fixtures/ux-remover' => $remover,
    ]);

    // Core itself always contributes real entries (Top/Footer/Body/Navigation defaults), so the
    // resolved collection is never empty -- assert the dangling targets just don't appear.
    expect(fn () => $manager->ux())->not->toThrow(Throwable::class)
        ->and($manager->ux()->pluck('id'))
        ->not->toContain('tests-fixtures-ux-adder::widget')
        ->not->toContain('tests-fixtures-ux-adder::gadget');
});
