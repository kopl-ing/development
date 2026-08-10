<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Kopling\Activitypub\ActivitypubActor;
use Kopling\Activitypub\ActivitypubDelivery;
use Kopling\Activitypub\Jobs\DeliverActivity;
use Kopling\Core\People\Person;
use Kopling\Core\Settings\Settings;

it('skips outbound delivery to a blocked domain', function () {
    Settings::set('kopling-activitypub::blocked-domains', "remote.example\nother.example");

    Http::fake();

    $person = Person::create(['name' => 'Blocklist Test', 'email' => 'blocklist@example.test', 'password' => 'secret']);
    ActivitypubActor::create(['person_id' => $person->id, 'handle' => 'blocklisttest']);

    $delivery = ActivitypubDelivery::create([
        'person_id' => $person->id,
        'inbox_url' => 'https://remote.example/inbox',
        'activity' => ['type' => 'Create'],
    ]);

    DeliverActivity::dispatchSync($delivery->id);

    Http::assertNothingSent();
    expect($delivery->fresh()->isDelivered())->toBeFalse();
});
