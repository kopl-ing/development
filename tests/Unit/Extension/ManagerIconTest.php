<?php

declare(strict_types=1);

it('prefixes a declared icon\'s id with the owning package id', function () {
    $manager = fakeManager([
        'tests-fixtures/icon-declarer' => [
            'namespace' => 'Tests\\Fixtures\\Extensions\\IconDeclarer\\',
            'path' => __DIR__,
        ],
    ]);

    $icon = $manager->icons()->get('tests-fixtures-icon-declarer::widget');

    expect($icon)->not->toBeNull()
        ->and($icon->label)->toBe('Widget')
        ->and($icon->default)->toBe('fas-cube');
});

it('lists every installed icon pack as [id => label]', function () {
    $manager = fakeManager([
        'tests-fixtures/icon-pack-declarer' => [
            'namespace' => 'Tests\\Fixtures\\Extensions\\IconPackDeclarer\\',
            'path' => __DIR__,
        ],
    ]);

    expect($manager->iconPackChoices())->toBe([
        'tests-fixtures-icon-pack-declarer' => 'Icon Pack Declarer Fixture',
    ]);
});

it('groups a declared icon pack\'s map by the owning package id, unprefixed keys', function () {
    $manager = fakeManager([
        'tests-fixtures/icon-pack-declarer' => [
            'namespace' => 'Tests\\Fixtures\\Extensions\\IconPackDeclarer\\',
            'path' => __DIR__,
        ],
    ]);

    $mappings = $manager->iconPackMappings();

    expect($mappings->get('tests-fixtures-icon-pack-declarer'))->toBe([
        'tests-fixtures-icon-declarer::widget' => 'fas-square',
        'tests-fixtures-icon-declarer::not-installed' => 'fas-circle',
    ]);
});

it('never validates an icon pack\'s mapped ids against icons() -- a foreign/uninstalled reference is left as-is', function () {
    $manager = fakeManager([
        'tests-fixtures/icon-pack-declarer' => [
            'namespace' => 'Tests\\Fixtures\\Extensions\\IconPackDeclarer\\',
            'path' => __DIR__,
        ],
    ]);

    // The declaring extension (icon-declarer) isn't installed here -- iconPackMappings() must
    // still return the pack's own map untouched, same tolerant handling ux()'s after()/before()
    // already gives a dangling reference.
    expect(fn () => $manager->iconPackMappings())->not->toThrow(Exception::class);
});
