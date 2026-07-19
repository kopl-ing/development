<?php

declare(strict_types=1);

use Kopling\Core\People\Group;
use Kopling\Core\People\Person;

/*
 * `Community\Chrome`'s sidebar is now a generic slot every portal's own layout populates --
 * Admin and Style Guide both reuse `Community\Navigation` itself (its own `$data['slot']`
 * override) as their one entry in that slot, rather than each hand-rolling their own `<ul
 * class="menu">`. These prove the reused mechanism actually renders each portal's own content,
 * not just that the pages load.
 */

it('shows Admin\'s own sidebar nav items (Settings, People, Groups) via the reused Navigation component', function () {
    $person = Person::create(['name' => 'Ada Lovelace', 'email' => 'ada@example.test', 'password' => 'secret']);

    $group = Group::create(['name' => 'Site Admins']);
    $group->givePermissionTo('kopling-admin::access-admin');
    $group->givePermissionTo('kopling-admin::manage-settings');
    $group->givePermissionTo('kopling-core::manage-people');
    $person->groups()->attach($group);

    $html = $this->actingAs($person)
        ->get(route('kopling-admin::admin/settings'))
        ->assertOk()
        ->getContent();

    expect($html)->toContain(__('kopling-admin::messages.settings'))
        ->and($html)->toContain(__('kopling-admin::messages.people'))
        ->and($html)->toContain(__('kopling-admin::messages.groups'));
});

it('shows Style Guide\'s own section anchors via the reused Navigation component', function () {
    $person = Person::create(['name' => 'Ada Lovelace', 'email' => 'ada@example.test', 'password' => 'secret']);

    $group = Group::create(['name' => 'Design Reviewers']);
    $group->givePermissionTo('kopling-style-guide::access-style-guide');
    $person->groups()->attach($group);

    $html = $this->actingAs($person)
        ->get(route('kopling-style-guide::style-guide/index'))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('href="#tokens"')
        ->toContain(__('kopling-style-guide::messages.tokens'))
        ->and($html)->toContain('href="#forms"')
        ->and($html)->toContain('href="#actions"')
        ->and($html)->toContain('href="#editor"')
        ->and($html)->toContain('href="#card"');
});
