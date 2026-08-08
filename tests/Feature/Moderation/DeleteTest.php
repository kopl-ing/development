<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Kopling\Moderation\Event\ContentDeleted;
use Kopling\Moderation\Flag;

it('denies a guest deleting', function () {
    $author = moderationPerson('bob@example.test');
    $moment = momentForModeration($author);
    $type = momentModerationType();

    $this->post("/_xhr/kopling-moderation/{$type}/{$moment->id}/delete")
        ->assertRedirect();

    $this->assertGuest();
    expect($moment->fresh())->not->toBeNull();
});

it('denies a person without moderate permission deleting', function () {
    $person = moderationPerson('nobody@example.test');
    $author = moderationPerson('bob@example.test');
    $moment = momentForModeration($author);
    $type = momentModerationType();

    $this->actingAs($person)
        ->post("/_xhr/kopling-moderation/{$type}/{$moment->id}/delete")
        ->assertForbidden();

    expect($moment->fresh())->not->toBeNull();
});

it('deletes a moment, actually removing the row', function () {
    $moderator = personWithModeratePermission();
    $author = moderationPerson('bob@example.test');
    $moment = momentForModeration($author);
    $type = momentModerationType();

    $this->actingAs($moderator)
        ->post("/_xhr/kopling-moderation/{$type}/{$moment->id}/delete", ['reason' => 'Confirmed spam'])
        ->assertRedirect();

    $this->assertDatabaseMissing('moments', ['id' => $moment->id]);
});

it('deleting a moment resolves its pending flags to actioned', function () {
    $moderator = personWithModeratePermission();
    $reporter = moderationPerson('eve@example.test');
    $author = moderationPerson('bob@example.test');
    $moment = momentForModeration($author);
    $type = momentModerationType();
    $this->actingAs($reporter)->post("/_xhr/kopling-moderation/{$type}/{$moment->id}", ['reason' => 'spam']);
    $flag = Flag::first();

    $this->actingAs($moderator)->post("/_xhr/kopling-moderation/{$type}/{$moment->id}/delete");

    expect($flag->fresh()->status)->toBe(Flag::STATUS_ACTIONED)
        ->and($flag->fresh()->resolved_by)->toBe($moderator->id);
});

it('fires ContentDeleted, giving other extensions a hook to cascade-clean their own resources', function () {
    // Same real-dispatcher-listener pattern as CardEventTest -- exercises the actual mechanism
    // end to end rather than Event::fake()'s isolated assertion, and doubles as the direct
    // demonstration of the cascade-hook this event exists for (a future images/attachments
    // extension listening the same way).
    $seen = null;
    Event::listen(ContentDeleted::class, function (ContentDeleted $event) use (&$seen) {
        $seen = $event;
    });

    $moderator = personWithModeratePermission();
    $author = moderationPerson('bob@example.test');
    $moment = momentForModeration($author);
    $type = momentModerationType();

    $this->actingAs($moderator)
        ->post("/_xhr/kopling-moderation/{$type}/{$moment->id}/delete", ['reason' => 'Confirmed spam']);

    expect($seen)->not->toBeNull()
        ->and($seen->subject->id)->toBe($moment->id)
        ->and($seen->reason)->toBe('Confirmed spam')
        ->and($seen->moderator->id)->toBe($moderator->id);
});
