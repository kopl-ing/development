<?php

declare(strict_types=1);

namespace Kopling\Activitypub\Listeners;

use Kopling\Activitypub\ActivitypubActor;
use Kopling\Activitypub\ActivitypubFollow;
use Kopling\Activitypub\Federation\Manager;
use Kopling\Core\Content\Moment;

class DeliverNewMomentListener
{
    public function __construct(protected Manager $federation)
    {
    }

    public function handle(Moment $moment): void
    {
        $person = $moment->person;
        $actor = $person->activitypubActor;

        if (! $actor?->isFederating()) {
            return;
        }

        $registration = $this->federation->federationForModel(Moment::class);

        if ($registration === null) {
            return;
        }

        $activity = $this->federation->toCreateActivity($registration, $moment, $person);

        $followerUris = ActivitypubFollow::query()
            ->where('following_person_id', $person->id)
            ->where('state', ActivitypubFollow::STATE_ACCEPTED)
            ->pluck('follower_uri');

        // Grouped by shared_inbox_url when the remote server has one -- one delivery per server,
        // not one per follower on it. Falls back to a follower's own inbox_url when it doesn't.
        $inboxes = ActivitypubActor::query()
            ->whereIn('remote_id', $followerUris)
            ->get()
            ->map(fn (ActivitypubActor $actor) => $actor->shared_inbox_url ?? $actor->inbox_url)
            ->filter()
            ->unique();

        foreach ($inboxes as $inboxUrl) {
            $this->federation->queueDelivery($person, $inboxUrl, $activity);
        }
    }
}
