<?php

declare(strict_types=1);

use Kopling\Activitypub\ActivitypubActor;
use Kopling\Core\Content\Moment;
use Kopling\Core\People\Person;

it('resolves webfinger to a JRD pointing at the actor URI', function () {
    $person = Person::create(['name' => 'Webfinger Person', 'email' => 'wf@example.test', 'password' => 'secret']);
    ActivitypubActor::create(['person_id' => $person->id, 'handle' => 'wfperson']);

    $host = parse_url((string) config('app.url'), PHP_URL_HOST);

    $response = $this->getJson("/.well-known/webfinger?resource=acct:wfperson@{$host}")
        ->assertOk()
        ->assertHeader('Content-Type', 'application/jrd+json');

    $response->assertJson([
        'subject' => "acct:wfperson@{$host}",
        'links' => [
            ['rel' => 'self', 'type' => 'application/activity+json'],
        ],
    ]);
    expect($response->json('links.0.href'))->toBe(route('kopling-activitypub::activitypub/people.show', $person));
});

it('404s webfinger for a handle that has not opted into federation', function () {
    $host = parse_url((string) config('app.url'), PHP_URL_HOST);

    $this->getJson("/.well-known/webfinger?resource=acct:nobody@{$host}")->assertStatus(404);
});

it('404s webfinger for the wrong domain even with a real handle', function () {
    $person = Person::create(['name' => 'Wrong Domain', 'email' => 'wd@example.test', 'password' => 'secret']);
    ActivitypubActor::create(['person_id' => $person->id, 'handle' => 'wrongdomain']);

    $this->getJson('/.well-known/webfinger?resource=acct:wrongdomain@not-this-instance.example')->assertStatus(404);
});

it('renders actor JSON-LD with inbox/outbox/publicKey', function () {
    $person = Person::create(['name' => 'Actor Person', 'email' => 'actor@example.test', 'password' => 'secret']);
    $actor = ActivitypubActor::create(['person_id' => $person->id, 'handle' => 'actorperson']);

    $uri = route('kopling-activitypub::activitypub/people.show', $person);

    $this->getJson($uri)
        ->assertOk()
        ->assertHeader('Content-Type', 'application/activity+json')
        ->assertJson([
            'id' => $uri,
            'type' => 'Person',
            'preferredUsername' => 'actorperson',
            'name' => 'Actor Person',
            'inbox' => "$uri/inbox",
            'outbox' => "$uri/outbox",
            'publicKey' => [
                'id' => "$uri#main-key",
                'owner' => $uri,
                'publicKeyPem' => $actor->public_key,
            ],
        ]);
});

it('404s a remote or non-federating Person\'s actor route', function () {
    $remote = Person::create(['name' => 'Remote', 'origin' => 'remote.example']);
    $this->getJson(route('kopling-activitypub::activitypub/people.show', $remote))->assertStatus(404);

    $noHandle = Person::create(['name' => 'No Handle', 'email' => 'nohandle@example.test', 'password' => 'secret']);
    $this->getJson(route('kopling-activitypub::activitypub/people.show', $noHandle))->assertStatus(404);
});

it('renders a Moment as a Note object with attributedTo', function () {
    $author = Person::create(['name' => 'Moment Author', 'email' => 'ma@example.test', 'password' => 'secret']);
    ActivitypubActor::create(['person_id' => $author->id, 'handle' => 'momentauthor']);
    $moment = Moment::create(['person_id' => $author->id, 'title' => 'Hi', 'body' => null, 'body_html' => '<p>hi</p>']);

    $objectUri = route('kopling-activitypub::activitypub/objects.show', ['type' => 'moments', 'id' => $moment->id]);
    $actorUri = route('kopling-activitypub::activitypub/people.show', $author);

    $this->getJson($objectUri)
        ->assertOk()
        ->assertJson([
            'id' => $objectUri,
            'type' => 'Note',
            'attributedTo' => $actorUri,
            'content' => '<p>hi</p>',
        ]);
});

it('404s an unknown object type or id', function () {
    $this->getJson(route('kopling-activitypub::activitypub/objects.show', ['type' => 'not-a-thing', 'id' => 'x']))->assertStatus(404);

    $author = Person::create(['name' => 'A', 'email' => 'a2@example.test', 'password' => 'secret']);
    $moment = Moment::create(['person_id' => $author->id, 'title' => 'Hi', 'body' => null, 'body_html' => '<p>hi</p>']);
    $moment->forceDelete();

    $this->getJson(route('kopling-activitypub::activitypub/objects.show', ['type' => 'moments', 'id' => $moment->id]))->assertStatus(404);
});
