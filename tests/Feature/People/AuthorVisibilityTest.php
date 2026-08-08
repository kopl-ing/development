<?php

declare(strict_types=1);

use Kopling\Core\Content\Moment;
use Kopling\Core\People\Person;
use Kopling\Core\People\Sanction;

function authorForVisibilityTest(string $email = 'author@example.test'): Person
{
    return Person::create(['name' => 'Author', 'email' => $email, 'password' => 'secret']);
}

it('shows a normal-visibility author\'s moment to everyone', function () {
    $author = authorForVisibilityTest();
    $moment = Moment::create(['person_id' => $author->id, 'title' => 'Visible Title', 'body' => 'Body']);

    $viewer = authorForVisibilityTest('viewer@example.test');

    $this->actingAs($viewer)->get(route('kopling-core::community/community'))
        ->assertOk()->assertSee($moment->title);
});

it('excludes a shadowbanned author\'s moment from another viewer\'s feed', function () {
    $moderator = authorForVisibilityTest('mod@example.test');
    $author = authorForVisibilityTest();
    $moment = Moment::create(['person_id' => $author->id, 'title' => 'Hidden Author Title', 'body' => 'Body']);

    Sanction::issue($author, ['visibility' => 'hidden', 'reason' => 'spam'], $moderator);

    $viewer = authorForVisibilityTest('viewer@example.test');

    $this->actingAs($viewer)->get(route('kopling-core::community/community'))
        ->assertOk()->assertDontSee($moment->title);
});

it('still shows a shadowbanned author their own moment', function () {
    $moderator = authorForVisibilityTest('mod@example.test');
    $author = authorForVisibilityTest();
    $moment = Moment::create(['person_id' => $author->id, 'title' => 'Own Hidden Title', 'body' => 'Body']);

    Sanction::issue($author, ['visibility' => 'hidden', 'reason' => 'spam'], $moderator);

    $this->actingAs($author)->get(route('kopling-core::community/community'))
        ->assertOk()->assertSee($moment->title);
});

it('excludes a shadowbanned author\'s moment from a guest\'s feed', function () {
    $moderator = authorForVisibilityTest('mod@example.test');
    $author = authorForVisibilityTest();
    $moment = Moment::create(['person_id' => $author->id, 'title' => 'Guest Hidden Title', 'body' => 'Body']);

    Sanction::issue($author, ['visibility' => 'hidden', 'reason' => 'spam'], $moderator);

    $this->get(route('kopling-core::community/community'))
        ->assertOk()->assertDontSee($moment->title);
});

it('shows the moment again once the shadowban is lifted', function () {
    $moderator = authorForVisibilityTest('mod@example.test');
    $author = authorForVisibilityTest();
    $moment = Moment::create(['person_id' => $author->id, 'title' => 'Restored Title', 'body' => 'Body']);
    Sanction::issue($author, ['visibility' => 'hidden', 'reason' => 'spam'], $moderator);

    Sanction::lift($author, $moderator);

    $viewer = authorForVisibilityTest('viewer@example.test');

    $this->actingAs($viewer)->get(route('kopling-core::community/community'))
        ->assertOk()->assertSee($moment->title);
});
