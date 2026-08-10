<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Kopling\Activitypub\ActivitypubActor;
use Kopling\Activitypub\ActivitypubObject;
use Kopling\Activitypub\Federation\HttpSignature;
use Kopling\Core\Content\Moment;
use Kopling\Core\People\Person;
use Kopling\Discussions\Reply;

it('ingests an inbound Create{Note} replying to a local Moment as a real Reply row', function () {
    $resource = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($resource, $privateKey);
    $publicKey = openssl_pkey_get_details($resource)['key'];

    $remoteActorUri = 'https://remote.example/users/dave';
    Http::fake([
        $remoteActorUri => Http::response([
            'type' => 'Person',
            'inbox' => 'https://remote.example/inbox',
            'publicKey' => ['publicKeyPem' => $publicKey],
        ], 200),
    ]);

    $author = Person::create(['name' => 'Moment Author', 'email' => 'momentauthor@example.test', 'password' => 'secret']);
    $moment = Moment::create(['person_id' => $author->id, 'title' => 'Hello', 'body' => null, 'body_html' => '<p>hi</p>']);

    $momentUri = route('kopling-activitypub::activitypub/objects.show', ['type' => 'moments', 'id' => $moment->id]);
    $inboxUrl = route('kopling-activitypub::activitypub/shared-inbox');

    $activity = [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'type' => 'Create',
        'actor' => $remoteActorUri,
        'object' => [
            'id' => 'https://remote.example/notes/1',
            'type' => 'Note',
            'inReplyTo' => $momentUri,
            'content' => '<p>Great post! <script>alert(1)</script><a href="https://example.com">link</a></p>',
        ],
    ];

    $body = json_encode($activity);
    $headers = HttpSignature::sign($privateKey, "$remoteActorUri#main-key", 'POST', $inboxUrl, $body);
    $server = $this->transformHeadersToServerVars([
        'Host' => $headers['Host'],
        'Date' => $headers['Date'],
        'Digest' => $headers['Digest'],
        'Signature' => $headers['Signature'],
        'Content-Type' => 'application/activity+json',
    ]);

    $this->call('POST', $inboxUrl, [], [], [], $server, $body)->assertStatus(202);

    $reply = Reply::where('moment_id', $moment->id)->first();

    expect($reply)->not->toBeNull();
    expect($reply->body)->toBe('');
    expect($reply->body_html)->toContain('Great post!')
        ->toContain('<a href="https://example.com"')
        ->not->toContain('<script>');

    $remoteAuthor = $reply->person;
    expect($remoteAuthor->isRemote())->toBeTrue();
    expect(ActivitypubActor::where('remote_id', $remoteActorUri)->where('person_id', $remoteAuthor->id)->exists())->toBeTrue();

    expect(ActivitypubObject::where('federatable_type', Reply::class)->where('federatable_id', $reply->id)->where('remote_id', 'https://remote.example/notes/1')->exists())->toBeTrue();
});
