<?php

declare(strict_types=1);

use Kopling\Core\Extension\Manager;

/*
 * Same reasoning as `IconTest`/`ThemeTest`: swaps the real, container-bound `Manager` singleton
 * for a `fakeManager()` built from disposable fixtures, so the real gate ("only shows with more
 * than one installed theme") is exercised deterministically, independent of which real
 * `ChangesTheme` extensions (theme-delft, theme-midnight, ...) happen to be installed.
 */
function swapThemeSwitcherThemes(array $extensions): void
{
    app()->instance(Manager::class, fakeManager($extensions));
}

it('renders nothing when at most one real theme is installed', function () {
    swapThemeSwitcherThemes([
        'tests-fixtures/theme-a' => [
            'namespace' => 'Tests\\Fixtures\\Extensions\\ThemeA\\',
            'path' => __DIR__.'/../../Fixtures/Extensions/ThemeA',
        ],
    ]);

    $html = (string) $this->blade('<x-k::community.theme-switcher />');

    expect(trim($html))->toBe('');
});

it('renders a toggle cycling to the next real installed theme when there is more than one', function () {
    swapThemeSwitcherThemes([
        'tests-fixtures/theme-a' => [
            'namespace' => 'Tests\\Fixtures\\Extensions\\ThemeA\\',
            'path' => __DIR__.'/../../Fixtures/Extensions/ThemeA',
        ],
        'tests-fixtures/theme-b' => [
            'namespace' => 'Tests\\Fixtures\\Extensions\\ThemeB\\',
            'path' => __DIR__.'/../../Fixtures/Extensions/ThemeB',
        ],
    ]);

    $html = (string) $this->blade('<x-k::community.theme-switcher />');

    expect($html)->toContain('name="theme" value="tests-fixtures-theme-b"');
});
