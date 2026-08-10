<?php

declare(strict_types=1);

use Kopling\Core\People\Group;
use Kopling\Core\People\Person;
use Kopling\Core\Settings\Settings;
use Symfony\Component\DomCrawler\Crawler;

/*
 * Core::adminSettings()'s community-name/community-logo/community-description, consumed by
 * Community\Chrome (name/logo, substituted only for the Community portal itself) and
 * layouts/partials/head.blade.php (the meta description, site-wide). Real HTTP requests against
 * the real Community/Admin/Style Guide portals, not fixtures -- proving the "only on Community"
 * scoping actually holds now that Chrome is shared across all three.
 */

it('shows the portal\'s own default label when nothing is configured', function () {
    $html = $this->get('/')->assertOk()->getContent();

    expect(new Crawler($html)->filter('title')->html())->toBe('Kopling')
        ->and(new Crawler($html)->filter('header span.text-lg')->html())->toBe('Community');
});

it('shows the configured community name instead of "Community" on the Community portal', function () {
    Settings::set('kopling-core::community-name', 'Acme Town Square');

    $html = $this->get('/')->assertOk()->getContent();

    expect(new Crawler($html)->filter('title')->html())->toBe('Acme Town Square')
        ->and(new Crawler($html)->filter('header span.text-lg')->html())->toBe('Acme Town Square');
});

it('shows the configured logo instead of the name once both are set', function () {
    Settings::set('kopling-core::community-name', 'Acme Town Square');
    Settings::set('kopling-core::community-logo', 'https://example.test/logo.png');

    $html = $this->get('/')->assertOk()->getContent();
    $header = new Crawler($html)->filter('header');

    // <title> legitimately still carries the plain name text (it can't hold an <img>), same
    // reasoning the Admin-portal test below only scopes its own check to <header> too.
    expect($header->filter('img')->attr('src'))->toBe('https://example.test/logo.png')
        ->and($header->filter('img')->attr('alt'))->toBe('Acme Town Square')
        ->and($header->filter('span.text-lg'))->toHaveCount(0);
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
    // community-name field's own current *value*, in its input. Scoping to <header> is what
    // proves Chrome itself didn't substitute it, regardless of what the rest of the page shows.
    $header = new Crawler($html)->filter('header');

    expect($header->filter('span.text-lg')->text())->toBe('Admin')
        ->and($header->filter('img'))->toHaveCount(0);
});

it('renders the meta description when configured, omits the tag entirely otherwise', function () {
    $html = $this->get('/')->assertOk()->getContent();

    expect(new Crawler($html)->filter('meta[name="description"]'))->toHaveCount(0);

    Settings::set('kopling-core::community-description', 'A friendly place to talk shop.');

    $html = $this->get('/')->assertOk()->getContent();

    expect(new Crawler($html)->filter('meta[name="description"]')->attr('content'))
        ->toBe('A friendly place to talk shop.');
});
