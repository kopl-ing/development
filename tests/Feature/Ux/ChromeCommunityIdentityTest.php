<?php

declare(strict_types=1);

use Kopling\Core\People\Group;
use Kopling\Core\People\Person;
use Kopling\Core\Settings\Settings;

/*
 * Core::adminSettings()'s community-name/community-logo/community-description, consumed by
 * Community\Chrome (name/logo, substituted only for the Community portal itself) and
 * layouts/partials/head.blade.php (the meta description, site-wide). Real HTTP requests against
 * the real Community/Admin/Style Guide portals, not fixtures -- proving the "only on Community"
 * scoping actually holds now that Chrome is shared across all three.
 */

it('shows the portal\'s own default label when nothing is configured', function () {
    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('>Community<');
});

it('shows the configured community name instead of "Community" on the Community portal', function () {
    Settings::set('kopling-core::community-name', 'Acme Town Square');

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('Acme Town Square')
        ->and($html)->not->toContain('>Community<');
});

it('shows the configured logo instead of the name once both are set', function () {
    Settings::set('kopling-core::community-name', 'Acme Town Square');
    Settings::set('kopling-core::community-logo', 'https://example.test/logo.png');

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('<img src="https://example.test/logo.png" alt="Acme Town Square"')
        ->and($html)->not->toContain('>Acme Town Square<');
});

it('does not substitute the community name on the Admin portal -- it keeps showing "Admin"', function () {
    Settings::set('kopling-core::community-name', 'Acme Town Square');

    $person = Person::create(['name' => 'Ada Lovelace', 'email' => 'ada@example.test', 'password' => 'secret']);
    $group = Group::create(['name' => 'Site Admins']);
    $group->givePermissionTo('kopling-admin::access-admin');
    $group->givePermissionTo('kopling-admin::manage-settings');
    $person->groups()->attach($group);

    $html = $this->actingAs($person)
        ->get(route('kopling-admin::admin/settings'))
        ->assertOk()
        ->getContent();

    // The settings page itself legitimately shows "Acme Town Square" elsewhere -- as the
    // community-name field's own current *value*, in its input. The scoped check is specifically
    // the topbar header, where Chrome would have substituted it had the "only on Community" rule
    // not held.
    $headerStart = strpos($html, '<header');
    $headerEnd = strpos($html, '</header>', $headerStart);
    $header = substr($html, $headerStart, $headerEnd - $headerStart);

    expect($header)->toContain('>Admin<')
        ->and($header)->not->toContain('Acme Town Square');
});

it('renders the meta description when configured, omits the tag entirely otherwise', function () {
    expect($this->get('/')->assertOk()->getContent())->not->toContain('name="description"');

    Settings::set('kopling-core::community-description', 'A friendly place to talk shop.');

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('<meta name="description" content="A friendly place to talk shop.">');
});
