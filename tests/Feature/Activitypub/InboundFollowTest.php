<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Kopling\Activitypub\ActivitypubActor;
use Kopling\Activitypub\ActivitypubFollow;
use Kopling\Activitypub\Federation\HttpSignature;
use Kopling\Core\People\Person;

function remoteKeyPair(): array
{
    $resource = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($resource, $privateKey);

    return [$privateKey, openssl_pkey_get_details($resource)['key']];
}

function signedInboxRequest(string $inboxUrl, string $keyId, string $privateKey, array $activity): array
{
    $body = json_encode($activity);
    $headers = HttpSignature::sign($privateKey, $keyId, 'POST', $inboxUrl, $body);

    return [$body, [
        'Host' => $headers['Host'],
        'Date' => $headers['Date'],
        'Digest' => $headers['Digest'],
        'Signature' => $headers['Signature'],
        'Content-Type' => 'application/activity+json',
    ]];
}

it('accepts a signed inbound Follow, records it, and delivers an Accept back', function () {
    [$privateKey, $publicKey] = remoteKeyPair();
    $remoteActorUri = 'https://remote.example/users/carol';
    $remoteInbox = 'https://remote.example/inbox';

    Http::fake([
        $remoteActorUri => Http::response([
            'type' => 'Person',
            'inbox' => $remoteInbox,
            'publicKey' => ['publicKeyPem' => $publicKey],
        ], 200, ['Content-Type' => 'application/activity+json']),
        $remoteInbox => Http::response('', 202),
    ]);

    $person = Person::create(['name' => 'Followed Local', 'email' => 'followed@example.test', 'password' => 'secret']);
    ActivitypubActor::create(['person_id' => $person->id, 'handle' => 'followed']);

    $localActorUri = route('kopling-activitypub::activitypub/people.show', ['person' => $person->id]);
    $inboxUrl = route('kopling-activitypub::activitypub/people.inbox', ['person' => $person->id]);

    $activity = [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id' => $remoteActorUri.'/follows/1',
        'type' => 'Follow',
        'actor' => $remoteActorUri,
        'object' => $localActorUri,
    ];

    [$body, $headers] = signedInboxRequest($inboxUrl, "$remoteActorUri#main-key", $privateKey, $activity);

    $response = $this->call('POST', $inboxUrl, [], [], [], $this->transformHeadersToServerVars($headers), $body);

    $response->assertStatus(202);

    expect(ActivitypubFollow::where('follower_uri', $remoteActorUri)->where('following_person_id', $person->id)->first())
        ->not->toBeNull()
        ->state->toBe(ActivitypubFollow::STATE_ACCEPTED);

    Http::assertSent(fn ($request) => $request->url() === $remoteInbox
        && json_decode($request->body(), true)['type'] === 'Accept');
});

it('rejects an inbound request whose signature does not match a tampered body', function () {
    [$privateKey, $publicKey] = remoteKeyPair();
    $remoteActorUri = 'https://remote.example/users/mallory';
    $remoteInbox = 'https://remote.example/inbox';

    Http::fake([
        $remoteActorUri => Http::response([
            'type' => 'Person',
            'inbox' => $remoteInbox,
            'publicKey' => ['publicKeyPem' => $publicKey],
        ], 200),
    ]);

    $person = Person::create(['name' => 'Target Local', 'email' => 'target@example.test', 'password' => 'secret']);
    ActivitypubActor::create(['person_id' => $person->id, 'handle' => 'target']);

    $localActorUri = route('kopling-activitypub::activitypub/people.show', ['person' => $person->id]);
    $inboxUrl = route('kopling-activitypub::activitypub/people.inbox', ['person' => $person->id]);

    $activity = [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'type' => 'Follow',
        'actor' => $remoteActorUri,
        'object' => $localActorUri,
    ];

    [$body, $headers] = signedInboxRequest($inboxUrl, "$remoteActorUri#main-key", $privateKey, $activity);

    // Tampered after signing -- the Digest/Signature headers still describe the original body.
    $tamperedBody = str_replace('Follow', 'Create', $body);

    $response = $this->call('POST', $inboxUrl, [], [], [], $this->transformHeadersToServerVars($headers), $tamperedBody);

    $response->assertStatus(401);
    expect(ActivitypubFollow::count())->toBe(0);
});

it('rejects an inbound request with no Signature header at all', function () {
    $person = Person::create(['name' => 'No Sig Local', 'email' => 'nosig@example.test', 'password' => 'secret']);
    ActivitypubActor::create(['person_id' => $person->id, 'handle' => 'nosig']);

    $inboxUrl = route('kopling-activitypub::activitypub/people.inbox', ['person' => $person->id]);

    $this->postJson($inboxUrl, ['type' => 'Follow'])->assertStatus(401);
});
