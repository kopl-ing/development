<?php

declare(strict_types=1);

namespace Kopling\Activitypub\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Kopling\Activitypub\ActivitypubFollow;
use Kopling\Activitypub\ActivitypubObject;
use Kopling\Activitypub\Federation\InboundHtmlSanitizer;
use Kopling\Activitypub\Federation\Manager;
use Kopling\Core\People\Person;

/**
 * Queued rather than processed inline off the (already signature-verified, but not yet
 * content-trusted) inbox request -- see `Controllers\InboxController`. Switches on the AP
 * `type`; `Accept`/`Delete`/`Like` are deliberately no-ops in v1 (this instance never sends a
 * `Follow` of its own to receive an `Accept` for, and Delete/Like aren't part of v1's scope --
 * see the federation plan's "Scope for v1"), not silently mishandled.
 */
class ProcessInboundActivity implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 60, 300];

    /**
     * @param  array<string, mixed>  $activity
     */
    public function __construct(
        public array $activity,
        public string $senderId,
    ) {
    }

    public function handle(Manager $federation): void
    {
        $sender = Person::find($this->senderId);

        if ($sender === null) {
            return;
        }

        match ($this->activity['type'] ?? null) {
            'Follow' => $this->handleFollow($federation, $sender),
            'Undo' => $this->handleUndo($federation),
            'Create' => $this->handleCreate($federation, $sender),
            default => null,
        };
    }

    protected function handleFollow(Manager $federation, Person $sender): void
    {
        $followingPerson = $this->localObjectPerson($federation);

        if ($followingPerson === null) {
            return;
        }

        // Auto-accepted -- v1 has no manual follow-approval workflow (see the federation plan's
        // "Scope for v1": no native "follow a person" feature exists to gate this on yet).
        $follow = ActivitypubFollow::updateOrCreate(
            ['follower_uri' => $this->activity['actor'], 'following_person_id' => $followingPerson->id],
            ['state' => ActivitypubFollow::STATE_ACCEPTED],
        );

        $senderActor = $sender->activitypubActor;

        if ($senderActor?->inbox_url === null) {
            return;
        }

        $federation->queueDelivery($followingPerson, $senderActor->inbox_url, [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $federation->canonicalActorUri($followingPerson)."/follows/{$follow->id}/accept",
            'type' => 'Accept',
            'actor' => $federation->canonicalActorUri($followingPerson),
            'object' => $this->activity,
        ]);
    }

    protected function handleUndo(Manager $federation): void
    {
        $inner = $this->activity['object'] ?? null;

        if (! is_array($inner) || ($inner['type'] ?? null) !== 'Follow') {
            return;
        }

        $followingPerson = $this->localObjectPerson($federation, $inner);

        if ($followingPerson === null) {
            return;
        }

        ActivitypubFollow::query()
            ->where('follower_uri', $inner['actor'] ?? $this->activity['actor'] ?? null)
            ->where('following_person_id', $followingPerson->id)
            ->delete();
    }

    /**
     * Never hardcodes "Reply" -- resolves against whichever `Extend\Federation` registration
     * matches the inbound object's own `type` and actually accepts inbound (`fromActivity` set;
     * `Moment`'s own registration deliberately has none, see core's `Core::federatedModels()`,
     * so an inbound `Create{Note}` can only ever become a `Reply` in v1, never a new `Moment` --
     * the federation plan's "Scope for v1" decision, enforced structurally here rather than by
     * a type-name check).
     */
    protected function handleCreate(Manager $federation, Person $sender): void
    {
        $object = $this->activity['object'] ?? null;

        if (! is_array($object) || ! is_string($object['type'] ?? null)) {
            return;
        }

        $registration = $federation->federationForInboundApType($object['type']);

        if ($registration === null) {
            return;
        }

        // Sanitized once, centrally, before any package-owned fromActivity() closure ever sees
        // it -- so accepting a new federatable model is never also an invitation to reimplement
        // this boundary per package. See Federation\InboundHtmlSanitizer's own docblock.
        if (isset($object['content']) && is_string($object['content'])) {
            $object['content'] = InboundHtmlSanitizer::sanitize($object['content']);
        }

        $model = ($registration->fromActivity)($object, $sender, fn (string $uri) => $federation->resolveObjectUri($uri));

        if ($model === null) {
            return;
        }

        ActivitypubObject::updateOrCreate(
            ['federatable_type' => $model::class, 'federatable_id' => $model->getKey()],
            ['remote_id' => $object['id'] ?? null, 'federated_at' => now()],
        );
    }

    /**
     * Resolves the local Person a Follow/Undo{Follow} activity's `object` names -- shared by
     * both handlers since `Undo` carries the original `Follow` (with its own `object`) nested
     * inside it.
     *
     * @param  array<string, mixed>|null  $activity
     */
    protected function localObjectPerson(Manager $federation, ?array $activity = null): ?Person
    {
        $objectUri = ($activity ?? $this->activity)['object'] ?? null;

        return is_string($objectUri) ? $federation->localPersonFromUri($objectUri) : null;
    }
}
