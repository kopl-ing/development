<?php

declare(strict_types=1);

use Kopling\Moderation\Flag;

it('denies a guest hiding', function () {
    $author = moderationPerson('bob@example.test');
    $moment = momentForModeration($author);
    $type = momentModerationType();

    $this->post("/_xhr/kopling-moderation/{$type}/{$moment->id}/hide")
        ->assertRedirect();

    $this->assertGuest();
    expect($moment->fresh()->trashed())->toBeFalse();
});

it('denies a person without moderate permission hiding', function () {
    $person = moderationPerson('nobody@example.test');
    $author = moderationPerson('bob@example.test');
    $moment = momentForModeration($author);
    $type = momentModerationType();

    $this->actingAs($person)
        ->post("/_xhr/kopling-moderation/{$type}/{$moment->id}/hide")
        ->assertForbidden();
});

it('hides a moment, soft-deleting it and setting the actor/reason', function () {
    $moderator = personWithModeratePermission();
    $author = moderationPerson('bob@example.test');
    $moment = momentForModeration($author);
    $type = momentModerationType();

    $this->actingAs($moderator)
        ->post("/_xhr/kopling-moderation/{$type}/{$moment->id}/hide", ['reason' => 'Spam content'])
        ->assertRedirect();

    $moment->refresh();

    expect($moment->trashed())->toBeTrue()
        ->and($moment->deleted_by)->toBe($moderator->id)
        ->and($moment->deleted_reason)->toBe('Spam content');
});

it('excludes a hidden moment from the feed', function () {
    $moderator = personWithModeratePermission();
    $author = moderationPerson('bob@example.test');
    $moment = momentForModeration($author);
    $type = momentModerationType();

    $this->get('/')->assertOk()->assertSee($moment->title);

    $this->actingAs($moderator)->post("/_xhr/kopling-moderation/{$type}/{$moment->id}/hide");

    $this->get('/')->assertOk()->assertDontSee($moment->title);
});

it('hiding a moment resolves its pending flags to actioned', function () {
    $moderator = personWithModeratePermission();
    $reporter = moderationPerson('eve@example.test');
    $author = moderationPerson('bob@example.test');
    $moment = momentForModeration($author);
    $type = momentModerationType();
    $this->actingAs($reporter)->post("/_xhr/kopling-moderation/{$type}/{$moment->id}", ['reason' => 'spam']);
    $flag = Flag::first();

    $this->actingAs($moderator)->post("/_xhr/kopling-moderation/{$type}/{$moment->id}/hide");

    expect($flag->fresh()->status)->toBe(Flag::STATUS_ACTIONED)
        ->and($flag->fresh()->resolved_by)->toBe($moderator->id);
});

it('unhides a moment, restoring it to the feed and clearing the actor/reason', function () {
    $moderator = personWithModeratePermission();
    $author = moderationPerson('bob@example.test');
    $moment = momentForModeration($author);
    $type = momentModerationType();
    $this->actingAs($moderator)->post("/_xhr/kopling-moderation/{$type}/{$moment->id}/hide", ['reason' => 'oops']);

    $this->actingAs($moderator)
        ->post("/_xhr/kopling-moderation/{$type}/{$moment->id}/unhide")
        ->assertRedirect();

    $moment->refresh();

    expect($moment->trashed())->toBeFalse()
        ->and($moment->deleted_by)->toBeNull()
        ->and($moment->deleted_reason)->toBeNull();

    $this->get('/')->assertOk()->assertSee($moment->title);
});
