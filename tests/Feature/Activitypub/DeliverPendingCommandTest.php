<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Kopling\Activitypub\ActivitypubActor;
use Kopling\Activitypub\ActivitypubDelivery;
use Kopling\Core\People\Person;

it('retries a stale undelivered delivery but skips a recent one', function () {
    Http::fake(['https://remote.example/inbox' => Http::response('', 202)]);

    $person = Person::create(['name' => 'Pending Test', 'email' => 'pending@example.test', 'password' => 'secret']);
    ActivitypubActor::create(['person_id' => $person->id, 'handle' => 'pendingtest']);

    $stale = ActivitypubDelivery::create([
        'person_id' => $person->id,
        'inbox_url' => 'https://remote.example/inbox',
        'activity' => ['type' => 'Create'],
    ]);
    $stale->forceFill(['updated_at' => now()->subMinutes(10)])->saveQuietly();

    $recent = ActivitypubDelivery::create([
        'person_id' => $person->id,
        'inbox_url' => 'https://remote.example/inbox',
        'activity' => ['type' => 'Create'],
    ]);

    $this->artisan('kopling:activitypub:deliver-pending')->assertSuccessful();

    expect($stale->fresh()->isDelivered())->toBeTrue();
    expect($recent->fresh()->isDelivered())->toBeFalse();
    Http::assertSentCount(1);
});
