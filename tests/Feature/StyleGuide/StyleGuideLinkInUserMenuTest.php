<?php

declare(strict_types=1);

use Kopling\Core\People\Group;
use Kopling\Core\People\Person;

/*
 * End-to-end (real Core + real Style Guide extension, not a fixture) proof that the style guide
 * link lives in the community topbar's user menu, gated by access-style-guide, with its own
 * brush icon -- see AdminLinkInUserMenuTest for the sibling entry, and UserMenuTest for the
 * mechanism itself in isolation.
 */

it('shows the style guide link, with its brush icon, inside the user menu for a person with access-style-guide', function () {
    $person = Person::create(['name' => 'Ada Lovelace', 'email' => 'ada@example.test', 'password' => 'secret']);

    $group = Group::create(['name' => 'Design Reviewers']);
    $group->givePermissionTo('kopling-style-guide::access-style-guide');
    $person->groups()->attach($group);

    $html = $this->actingAs($person)->get('/')->assertOk()->getContent();

    expect($html)->toContain('Style Guide');

    $brushSvg = svg('fas-brush', '', ['width' => '1em', 'height' => '1em'])->toHtml();

    expect($brushSvg)->not->toBe('')
        ->and($html)->toContain($brushSvg);
});

it('does not show the style guide link for a signed-in person without access-style-guide', function () {
    $person = Person::create(['name' => 'Ada Lovelace', 'email' => 'ada@example.test', 'password' => 'secret']);

    $html = $this->actingAs($person)->get('/')->assertOk()->getContent();

    expect($html)->not->toContain('Style Guide');
});
