<?php

declare(strict_types=1);

use Kopling\Core\People\Group;
use Kopling\Core\People\Person;

/*
 * The style guide's own layout (layouts/style-guide.blade.php) registers `UserMenu::class` into
 * its own topbar slot (Extension::ux()) -- this is the same avatar dropdown Community's chrome
 * renders, just reached from a different page, giving a person browsing the style guide a way
 * back to Community (Core's own default entry) and, if permitted, Admin -- not only a browser-
 * back button.
 */

it('shows the user menu, with Community and Admin, inside the style guide\'s own topbar', function () {
    $person = Person::create(['name' => 'Ada Lovelace', 'email' => 'ada@example.test', 'password' => 'secret']);

    $group = Group::create(['name' => 'Site Admins']);
    $group->givePermissionTo('kopling-style-guide::access-style-guide');
    $group->givePermissionTo('kopling-admin::access-admin');
    $person->groups()->attach($group);

    $html = $this->actingAs($person)
        ->get(route('kopling-style-guide::style-guide/index'))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('popover')
        ->and($html)->toContain(__('kopling-core::community.community'))
        ->and($html)->toContain(__('kopling-admin::messages.admin_panel'));
});

it('shows only Community, not Admin, for a style guide visitor without access-admin', function () {
    $person = Person::create(['name' => 'Ada Lovelace', 'email' => 'ada@example.test', 'password' => 'secret']);

    $group = Group::create(['name' => 'Design Reviewers']);
    $group->givePermissionTo('kopling-style-guide::access-style-guide');
    $person->groups()->attach($group);

    $html = $this->actingAs($person)
        ->get(route('kopling-style-guide::style-guide/index'))
        ->assertOk()
        ->getContent();

    expect($html)->toContain(__('kopling-core::community.community'))
        ->and($html)->not->toContain(__('kopling-admin::messages.admin_panel'));
});
