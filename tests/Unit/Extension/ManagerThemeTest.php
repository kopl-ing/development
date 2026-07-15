<?php

declare(strict_types=1);

use Kopling\Core\Ux\Theme\ColorScheme;

it('groups a declared theme\'s tokens by the owning package id, unprefixed keys', function () {
    $manager = fakeManager([
        'tests-fixtures/theme-declarer' => [
            'namespace' => 'Tests\\Fixtures\\Extensions\\ThemeDeclarer\\',
            'path' => __DIR__,
        ],
    ]);

    $themes = $manager->themes();

    expect($themes->get('tests-fixtures-theme-declarer'))->toBe(['--color-accent' => '#ff0000']);
});

it('throws when a ChangesTheme extension declares a key that is not a real Token', function () {
    $manager = fakeManager([
        'tests-fixtures/bad-theme-token' => [
            'namespace' => 'Tests\\Fixtures\\Extensions\\BadThemeToken\\',
            'path' => __DIR__,
        ],
    ]);

    expect(fn () => $manager->themes())
        ->toThrow(InvalidArgumentException::class, 'unrecognized theme token');
});

it('throws when a ChangesTheme extension declares a value that does not match its Token\'s expected shape', function () {
    $manager = fakeManager([
        'tests-fixtures/bad-theme-value' => [
            'namespace' => 'Tests\\Fixtures\\Extensions\\BadThemeValue\\',
            'path' => __DIR__,
        ],
    ]);

    expect(fn () => $manager->themes())
        ->toThrow(InvalidArgumentException::class, 'invalid value');
});

it('themeColorSchemes() groups each declared theme\'s colorScheme() by the owning package id', function () {
    $manager = fakeManager([
        'tests-fixtures/theme-declarer' => [
            'namespace' => 'Tests\\Fixtures\\Extensions\\ThemeDeclarer\\',
            'path' => __DIR__,
        ],
    ]);

    $schemes = $manager->themeColorSchemes();

    expect($schemes->get('tests-fixtures-theme-declarer'))->toBe(ColorScheme::Light);
});
