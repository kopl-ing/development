<?php

declare(strict_types=1);

namespace Kopling\Activitypub\Controllers;

use Illuminate\Http\JsonResponse;
use Kopling\Activitypub\Federation\Manager;
use Kopling\Core\People\Person;

class ActorController
{
    public function __construct(protected Manager $federation)
    {
    }

    public function __invoke(Person $person): JsonResponse
    {
        $actor = $person->activitypubActor;

        abort_unless($person->isLocal() && $actor?->isFederating(), 404);

        $uri = $this->federation->canonicalActorUri($person);

        return response()->json([
            '@context' => [
                'https://www.w3.org/ns/activitystreams',
                'https://w3id.org/security/v1',
            ],
            'id' => $uri,
            'type' => 'Person',
            'preferredUsername' => $actor->handle,
            'name' => $person->name,
            // The inbox/outbox routes themselves ship in Phase 4 -- this path convention is
            // fixed now so both sides agree on it without a route existing yet to reference.
            'inbox' => "$uri/inbox",
            'outbox' => "$uri/outbox",
            'publicKey' => [
                'id' => "$uri#main-key",
                'owner' => $uri,
                'publicKeyPem' => $actor->public_key,
            ],
        ])->header('Content-Type', 'application/activity+json');
    }
}
