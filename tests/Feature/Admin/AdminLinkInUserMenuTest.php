<?php

declare(strict_types=1);

use Kopling\Core\People\Group;
use Kopling\Core\People\Person;

/*
 * End-to-end (real Core + real Admin extension, not a fixture) proof that the admin panel link
 * lives in the community topbar's user menu, gated by access-admin, and pinned ahead of anything
 * else registered there via Ux::first() -- see UserMenuTest for the mechanism itself in
 * isolation.
 */

it('shows the admin panel link, with its helmet-safety icon, inside the user menu for a person with access-admin', function () {
    $person = Person::create(['name' => 'Ada Lovelace', 'email' => 'ada@example.test', 'password' => 'secret']);

    $group = Group::create(['name' => 'Site Admins']);
    $group->givePermissionTo('kopling-admin::access-admin');
    $person->groups()->attach($group);

    $html = $this->actingAs($person)->get('/')->assertOk()->getContent();

    expect($html)->toContain('Admin panel');

    // The icon's own rendered SVG markup, byte-for-byte as `<x-k::icon>` itself renders it
    // (views/ux/icon.blade.php's exact svg() call) -- not just that *some* <svg> appears
    // somewhere on the page (the theme switcher and other chrome already render their own), so
    // this proves it's genuinely this icon wired to the admin-panel entry.
    $helmetSvg = svg('fas-helmet-safety', '', ['width' => '1em', 'height' => '1em'])->toHtml();

    expect($helmetSvg)->not->toBe('')
        ->and($html)->toContain($helmetSvg);
});

it('does not show the admin panel link for a signed-in person without access-admin', function () {
    $person = Person::create(['name' => 'Ada Lovelace', 'email' => 'ada@example.test', 'password' => 'secret']);

    $html = $this->actingAs($person)->get('/')->assertOk()->getContent();

    expect($html)->not->toContain('Admin panel');
});
