<?php

declare(strict_types=1);

use Kopling\Core\Extension\Manager;
use Kopling\Core\Ux\Theme;
use Kopling\Core\Ux\Theme\ThemeToken;

/*
 * Theme::available()/active()/css() all resolve `app(Manager::class)` directly rather than
 * accepting one as a dependency, so these swap the real, container-bound singleton for a
 * `fakeManager()` instance (see tests/Pest.php) built from disposable fixtures -- deterministic
 * regardless of which real ChangesTheme extensions (theme-delft, theme-midnight, ...) happen to
 * be installed, and able to exercise "no theme installed at all" without uninstalling anything.
 */

$themeA = ['namespace' => 'Tests\\Fixtures\\Extensions\\ThemeA\\', 'path' => __DIR__];
$themeB = ['namespace' => 'Tests\\Fixtures\\Extensions\\ThemeB\\', 'path' => __DIR__];

function swapThemes(array $extensions): void
{
    app()->instance(Manager::class, fakeManager($extensions));
}

it('available() lists every installed ChangesTheme extension, keyed by id and labelled by name()', function () use ($themeA, $themeB) {
    swapThemes([
        'tests-fixtures/theme-a' => $themeA,
        'tests-fixtures/theme-b' => $themeB,
    ]);

    expect(Theme::available())->toBe([
        'tests-fixtures-theme-a' => 'Theme A',
        'tests-fixtures-theme-b' => 'Theme B',
    ]);
});

it('active() is null when no theme extension is installed', function () {
    swapThemes([]);

    expect(Theme::active())->toBeNull();
});

it('active() defaults to the alphabetically-first installed theme id when no cookie is set', function () use ($themeA, $themeB) {
    // Registered out of alphabetical order deliberately -- the default must come from sorting
    // ids, not from discovery/registration order.
    swapThemes([
        'tests-fixtures/theme-b' => $themeB,
        'tests-fixtures/theme-a' => $themeA,
    ]);

    expect(Theme::active())->toBe('tests-fixtures-theme-a');
});

it('active() honours the visitor\'s cookie when it names a still-installed theme', function () use ($themeA, $themeB) {
    swapThemes([
        'tests-fixtures/theme-a' => $themeA,
        'tests-fixtures/theme-b' => $themeB,
    ]);

    request()->cookies->set(Theme::COOKIE, 'tests-fixtures-theme-b');

    expect(Theme::active())->toBe('tests-fixtures-theme-b');
});

it('active() falls back to the deterministic default when the cookie names a theme that isn\'t installed', function () use ($themeA, $themeB) {
    swapThemes([
        'tests-fixtures/theme-a' => $themeA,
        'tests-fixtures/theme-b' => $themeB,
    ]);

    request()->cookies->set(Theme::COOKIE, 'not-installed');

    expect(Theme::active())->toBe('tests-fixtures-theme-a');
});

it('css() is an empty string when no theme is installed and no ThemeToken rows exist', function () {
    swapThemes([]);

    expect(Theme::css())->toBe('');
});

it('css() renders only the active theme\'s tokens, never every installed theme merged together', function () use ($themeA, $themeB) {
    swapThemes([
        'tests-fixtures/theme-a' => $themeA,
        'tests-fixtures/theme-b' => $themeB,
    ]);

    $css = Theme::css();

    expect($css)->toBe(':root[data-theme="kopling"]{--color-primary:#111111;}')
        ->and($css)->not->toContain('#222222');
});

it('css() lets a ThemeToken row override the active theme\'s value for that token', function () use ($themeA) {
    swapThemes(['tests-fixtures/theme-a' => $themeA]);

    ThemeToken::create(['token' => '--color-primary', 'value' => '#333333']);

    expect(Theme::css())->toBe(':root[data-theme="kopling"]{--color-primary:#333333;}');
});

it('css() lets a ThemeToken row add a token the active theme never declared', function () use ($themeA) {
    swapThemes(['tests-fixtures/theme-a' => $themeA]);

    ThemeToken::create(['token' => '--color-accent', 'value' => '#abcdef']);

    expect(Theme::css())->toBe(
        ':root[data-theme="kopling"]{--color-primary:#111111;--color-accent:#abcdef;}'
    );
});

it('css() silently skips a ThemeToken row whose key is not a real Token', function () use ($themeA) {
    swapThemes(['tests-fixtures/theme-a' => $themeA]);

    ThemeToken::create(['token' => '--not-a-real-token', 'value' => '#abcdef']);

    expect(Theme::css())->toBe(':root[data-theme="kopling"]{--color-primary:#111111;}');
});

it('css() silently skips a ThemeToken row whose value does not match its Token\'s expected shape', function () use ($themeA) {
    swapThemes(['tests-fixtures/theme-a' => $themeA]);

    ThemeToken::create(['token' => '--color-accent', 'value' => 'not-a-hex-color']);

    expect(Theme::css())->toBe(':root[data-theme="kopling"]{--color-primary:#111111;}');
});
