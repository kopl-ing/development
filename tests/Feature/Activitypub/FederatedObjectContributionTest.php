<?php

declare(strict_types=1);

use Kopling\Activitypub\ActivitypubActor;
use Kopling\Core\Content\Moment;
use Kopling\Core\Extension\Manager;
use Kopling\Core\People\Person;

it('merges an ExtendsFederatedObjects contribution into a Moment\'s outbound JSON-LD, without the envelope being overridable', function () {
    app()->instance(Manager::class, fakeManager([
        'tests-fixtures/federated-object-contributor-fixture' => [
            'namespace' => 'Tests\\Fixtures\\Extensions\\FederatedObjectContributorFixture\\',
            'path' => base_path('tests/Fixtures/Extensions/FederatedObjectContributorFixture'),
        ],
    ]));

    $author = Person::create(['name' => 'Gallery Author', 'email' => 'gallery@example.test', 'password' => 'secret']);
    ActivitypubActor::create(['person_id' => $author->id, 'handle' => 'galleryauthor']);
    $moment = Moment::create(['person_id' => $author->id, 'title' => 'Hi', 'body' => null, 'body_html' => '<p>hi</p>']);

    $objectUri = route('kopling-activitypub::activitypub/objects.show', ['type' => 'moments', 'id' => $moment->id]);

    $response = $this->getJson($objectUri)->assertOk();

    // The fixture's contribution deliberately tries to override "type" -- the envelope wins.
    $response->assertJson([
        'id' => $objectUri,
        'type' => 'Note',
        'attachment' => [
            ['type' => 'Image', 'url' => "https://example.test/fixtures/{$moment->id}.jpg"],
        ],
    ]);
});
