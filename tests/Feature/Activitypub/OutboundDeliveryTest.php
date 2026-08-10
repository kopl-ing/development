<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Kopling\Activitypub\ActivitypubActor;
use Kopling\Activitypub\ActivitypubFollow;
use Kopling\Activitypub\Federation\HttpSignature;
use Kopling\Core\Content\Moment;
use Kopling\Core\People\Person;

function federatingPerson(string $handle): Person
{
    $person = Person::create(['name' => 'Local Federator', 'email' => "$handle@example.test", 'password' => 'secret']);

    ActivitypubActor::create(['person_id' => $person->id, 'handle' => $handle]);

    return $person->fresh();
}

function remoteFollowerActor(Person $following, string $inboxUrl, ?string $sharedInboxUrl = null): ActivitypubActor
{
    $remotePerson = Person::create(['name' => 'Remote Follower', 'origin' => 'remote.example']);

    $actor = ActivitypubActor::create([
        'person_id' => $remotePerson->id,
        'remote_id' => 'https://remote.example/users/'.$remotePerson->id,
        'inbox_url' => $inboxUrl,
        'shared_inbox_url' => $sharedInboxUrl,
        'public_key' => 'unused-for-outbound-test',
    ]);

    ActivitypubFollow::create([
        'follower_uri' => $actor->remote_id,
        'following_person_id' => $following->id,
        'state' => ActivitypubFollow::STATE_ACCEPTED,
    ]);

    return $actor;
}

it('signs and delivers a Create{Note} to an accepted follower on new Moment', function () {
    Http::fake(['https://remote.example/inbox' => Http::response('', 202)]);

    $person = federatingPerson('alice');
    $actor = $person->activitypubActor;
    remoteFollowerActor($person, 'https://remote.example/inbox');

    Moment::create(['person_id' => $person->id, 'title' => 'Hello', 'body' => null, 'body_html' => '<p>hi</p>']);

    Http::assertSent(function ($request) use ($actor) {
        $headers = collect($request->headers())->mapWithKeys(fn ($v, $k) => [strtolower($k) => $v[0]])->all();

        expect(HttpSignature::verify($actor->public_key, 'POST', '/inbox', $headers, $request->body()))->toBeTrue();

        $body = json_decode($request->body(), true);
        expect($body['type'])->toBe('Create');
        expect($body['object']['type'])->toBe('Note');
        expect($body['object']['content'])->toBe('<p>hi</p>');

        return $request->url() === 'https://remote.example/inbox';
    });
});

it('sends one delivery per shared inbox, not one per follower', function () {
    Http::fake(['https://remote.example/shared-inbox' => Http::response('', 202)]);

    $person = federatingPerson('bob');
    remoteFollowerActor($person, 'https://remote.example/inbox-1', 'https://remote.example/shared-inbox');
    remoteFollowerActor($person, 'https://remote.example/inbox-2', 'https://remote.example/shared-inbox');

    Moment::create(['person_id' => $person->id, 'title' => 'Hello', 'body' => null, 'body_html' => '<p>hi</p>']);

    Http::assertSentCount(1);
});

it('never delivers for a Person who has not opted into federation', function () {
    Http::fake();

    $person = Person::create(['name' => 'No Federation', 'email' => 'nofed@example.test', 'password' => 'secret']);
    Moment::create(['person_id' => $person->id, 'title' => 'Hello', 'body' => null, 'body_html' => '<p>hi</p>']);

    Http::assertNothingSent();
});
