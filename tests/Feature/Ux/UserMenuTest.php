<?php

declare(strict_types=1);

use Kopling\Core\Extension\Manager;
use Kopling\Core\People\Person;

/*
 * `UserMenu` resolves `UserMenu::SLOT` the same way `Card\Control` does -- these swap the real,
 * container-bound `Manager` singleton for a `fakeManager()` instance built from disposable
 * fixtures, so a real extension being installed or not can't affect the assertions (same
 * approach `CardControlTest`/`ThemeSwitcherTest` already use).
 */
function swapUserMenuEntries(array $extensions): void
{
    app()->instance(Manager::class, fakeManager($extensions));
}

it('renders nothing for a guest', function () {
    swapUserMenuEntries([]);

    $html = (string) $this->blade('<x-k::community.user-menu />');

    expect(trim($html))->toBe('');
});

it('renders a dropdown with just Core\'s own "Community" default when no extension adds anything else', function () {
    // fakeManager() always prepends real Core regardless of the fixture list given (see its own
    // docblock) -- and Core's own UserMenu::defaults() now always registers a "community-link"
    // default into its own SLOT, so there's no longer a real "the menu has zero entries" case to
    // reach through the actual Manager; the bare-avatar-no-dropdown branch in user-menu.blade.php
    // itself is still there for defensive completeness, it's just not reachable here anymore.
    swapUserMenuEntries([]);

    $person = Person::create(['name' => 'Ada Lovelace', 'email' => 'ada@example.test', 'password' => 'secret']);

    $html = (string) $this->actingAs($person)->blade('<x-k::community.user-menu />');

    expect($html)->toContain('AL')
        ->and($html)->toContain('popover')
        ->and($html)->toContain(__('kopling-core::community.community'));
});

it('renders a dropdown once an extension adds an entry to UserMenu::SLOT', function () {
    swapUserMenuEntries([
        'tests-fixtures/user-menu-entry' => [
            'namespace' => 'Tests\\Fixtures\\Extensions\\UserMenuEntry\\',
            'path' => __DIR__.'/../../Fixtures/Extensions/UserMenuEntry',
        ],
    ]);

    $person = Person::create(['name' => 'Ada Lovelace', 'email' => 'ada@example.test', 'password' => 'secret']);

    $html = (string) $this->actingAs($person)->blade('<x-k::community.user-menu />');

    expect($html)->toContain('popover')
        ->and($html)->toContain('Fixture Item');
});

it('pins a first() entry ahead of one registered without it, regardless of registration order', function () {
    swapUserMenuEntries([
        'tests-fixtures/user-menu-ordering' => [
            'namespace' => 'Tests\\Fixtures\\Extensions\\UserMenuOrdering\\',
            'path' => __DIR__.'/../../Fixtures/Extensions/UserMenuOrdering',
        ],
    ]);

    $person = Person::create(['name' => 'Ada Lovelace', 'email' => 'ada@example.test', 'password' => 'secret']);

    $html = (string) $this->actingAs($person)->blade('<x-k::community.user-menu />');

    expect(strpos($html, 'Pinned Item'))->toBeLessThan(strpos($html, 'Second Item'));
});
