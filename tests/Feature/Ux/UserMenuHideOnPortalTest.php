<?php

declare(strict_types=1);

use Kopling\Core\People\Group;
use Kopling\Core\People\Person;

/*
 * End-to-end (real Core + real Admin + real Style Guide) proof that each of the three
 * UserMenu::SLOT entries (Core's own "community-link", Admin's "admin-link", Style Guide's
 * "style-guide-link") hides itself via Item's hideOnPortal while its own portal is the one
 * currently being viewed -- Context::isPortal()'s own mechanism, see ContextTest, exercised here
 * through the real registrations rather than in isolation.
 *
 * Assertions are scoped to the dropdown's own markup, not the whole page: a portal's own label
 * ("Community", "Admin") already appears elsewhere as that page's own heading regardless of this
 * feature, and the style guide's own showcase content demonstrates `<x-k::link>` against its own
 * route as an unrelated demo -- neither says anything about whether hideOnPortal worked.
 */

function personWithFullMenuAccess(): Person
{
    $person = Person::create(['name' => 'Ada Lovelace', 'email' => 'ada@example.test', 'password' => 'secret']);

    $group = Group::create(['name' => 'Everything']);
    $group->givePermissionTo('kopling-admin::access-admin');
    $group->givePermissionTo('kopling-admin::manage-settings');
    $group->givePermissionTo('kopling-style-guide::access-style-guide');
    $person->groups()->attach($group);

    return $person;
}

/**
 * Isolates the user menu's own dropdown `<ul>` -- from its `aria-label` (unique to this
 * dropdown, "Account menu") up to the next `</ul>`, which closes it (Item's own `<li>` entries
 * never nest another `<ul>` inside).
 */
function extractUserMenuHtml(string $html): string
{
    $start = strpos($html, 'aria-label="'.__('kopling-core::community.account_menu').'"');
    $end = strpos($html, '</ul>', $start);

    return substr($html, $start, $end - $start);
}

it('hides "Community" on the Community portal itself, but shows Admin and Style Guide', function () {
    $person = personWithFullMenuAccess();

    $html = $this->actingAs($person)->get('/')->assertOk()->getContent();
    $menu = extractUserMenuHtml($html);

    expect($menu)->not->toContain(__('kopling-core::community.community'))
        ->and($menu)->toContain(__('kopling-admin::messages.admin_panel'))
        ->and($menu)->toContain(__('kopling-style-guide::messages.title'));
});

it('hides "Admin panel" on the Admin portal itself, but shows Community and Style Guide', function () {
    $person = personWithFullMenuAccess();

    $html = $this->actingAs($person)
        ->get(route('kopling-admin::admin/settings'))
        ->assertOk()
        ->getContent();
    $menu = extractUserMenuHtml($html);

    expect($menu)->not->toContain(__('kopling-admin::messages.admin_panel'))
        ->and($menu)->toContain(__('kopling-core::community.community'))
        ->and($menu)->toContain(__('kopling-style-guide::messages.title'));
});

it('hides "Style Guide" on the Style Guide portal itself, but shows Community and Admin', function () {
    $person = personWithFullMenuAccess();

    $html = $this->actingAs($person)
        ->get(route('kopling-style-guide::style-guide/index'))
        ->assertOk()
        ->getContent();
    $menu = extractUserMenuHtml($html);

    expect($menu)->not->toContain(__('kopling-style-guide::messages.title'))
        ->and($menu)->toContain(__('kopling-core::community.community'))
        ->and($menu)->toContain(__('kopling-admin::messages.admin_panel'));
});
