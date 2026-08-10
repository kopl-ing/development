<?php

declare(strict_types=1);

namespace Kopling\Activitypub\Federation;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Kopling\Activitypub\ActivitypubActor;
use Kopling\Activitypub\ActivitypubDelivery;
use Kopling\Activitypub\ActivitypubObject;
use Kopling\Activitypub\Jobs\DeliverActivity;
use Kopling\Core\Extend\Federation;
use Kopling\Core\Extension\Manager as ExtensionManager;
use Kopling\Core\People\Person;
use Kopling\Core\Settings\Settings;
use RuntimeException;

/**
 * `k-extensions/activitypub`'s own manager -- not to be confused with
 * `Kopling\Core\Extension\Manager` (injected here as `$extensions`), which this reads
 * `federatedModels()` from. Named to match the plan's own `Federation\Manager::resolveActor()`/
 * `Federation\Jobs\*` references.
 */
class Manager
{
    /**
     * How long a fetched remote actor document stays trusted before `resolveActor()` refetches
     * it -- long enough that verifying a burst of inbound activities from the same actor doesn't
     * refetch per request, short enough that a remote's rotated key is picked up within a day.
     */
    protected const ACTOR_FRESH_FOR_HOURS = 24;

    public function __construct(protected ExtensionManager $extensions)
    {
    }

    public function isDomainBlocked(string $host): bool
    {
        $blocked = (string) Settings::get('kopling-activitypub::blocked-domains', '');

        $domains = array_filter(array_map('trim', explode("\n", $blocked)));

        return in_array(strtolower($host), array_map('strtolower', $domains), true);
    }

    /**
     * The one entry point for outbound delivery -- persists the attempt first (see
     * `ActivitypubDelivery`'s own docblock for why), then dispatches `DeliverActivity` by that
     * row's id, never by raw person/inbox/activity args, so a retry (queue-driven or
     * `federation:deliver-pending`'s own cron-driven one) always re-reads the exact same
     * already-recorded attempt rather than reconstructing a payload that might have drifted.
     *
     * @param  array<string, mixed>  $activity
     */
    public function queueDelivery(Person $person, string $inboxUrl, array $activity): void
    {
        $delivery = ActivitypubDelivery::create([
            'person_id' => $person->id,
            'inbox_url' => $inboxUrl,
            'activity' => $activity,
        ]);

        DeliverActivity::dispatch($delivery->id);
    }

    /**
     * The "fake People" idea, made concrete -- if an `activitypub_actors` row for this URI
     * exists and is fresh, return its `Person`; otherwise fetch the actor document and
     * upsert both a `Person` row (origin = the actor's own host) and its `activitypub_actors`
     * row (public key, inbox URLs) from it. A URI that's actually this instance's own resolves
     * to the existing local `Person` directly -- never fetched over HTTP, never turned into a
     * second, "remote" row for a person who's already local.
     */
    public function resolveActor(string $uri): ?Person
    {
        if ($local = $this->localPersonFromUri($uri)) {
            return $local;
        }

        $existing = ActivitypubActor::where('remote_id', $uri)->first();

        if ($existing?->fetched_at?->isAfter(now()->subHours(static::ACTOR_FRESH_FOR_HOURS))) {
            return $existing->person;
        }

        $response = Http::withHeaders(['Accept' => 'application/activity+json'])->get($uri);

        if ($response->failed()) {
            return $existing?->person;
        }

        $document = $response->json();

        if (! is_array($document) || ($document['type'] ?? null) !== 'Person') {
            return $existing?->person;
        }

        $person = $existing?->person ?? Person::create([
            'name' => $document['name'] ?? $document['preferredUsername'] ?? $uri,
            'origin' => parse_url($uri, PHP_URL_HOST),
        ]);

        ActivitypubActor::updateOrCreate(
            ['remote_id' => $uri],
            [
                'person_id' => $person->id,
                'inbox_url' => $document['inbox'] ?? null,
                'shared_inbox_url' => $document['endpoints']['sharedInbox'] ?? null,
                'outbox_url' => $document['outbox'] ?? null,
                'public_key' => $document['publicKey']['publicKeyPem'] ?? null,
                'fetched_at' => now(),
            ],
        );

        return $person;
    }

    /**
     * Parses this instance's own `/ap/people/{id}` URI shape back to a local `Person`, without
     * any network call -- used both by `resolveActor()` (a "remote" actor URI that's actually
     * our own) and by inbound Follow/Undo handling (resolving the local Person an activity's
     * `object` names). Null for any URI that isn't recognizably one of ours.
     */
    public function localPersonFromUri(string $uri): ?Person
    {
        if (parse_url($uri, PHP_URL_HOST) !== parse_url((string) config('app.url'), PHP_URL_HOST)) {
            return null;
        }

        $id = Str::afterLast(rtrim(parse_url($uri, PHP_URL_PATH) ?? '', '/'), '/');

        return Person::whereNull('origin')->find($id);
    }

    /**
     * @return Federation|null
     */
    public function federationForInboundApType(string $apType)
    {
        return $this->extensions->federatedModels()
            ->first(fn (Federation $registration) => $registration->apType === $apType && $registration->fromActivity !== null);
    }

    /**
     * Resolves any AP object URI back to whatever local model it maps to -- this instance's own
     * `/ap/{type}/{id}` URI parsed directly (no network round-trip to fetch our own object back),
     * or a previously-ingested remote object looked up by `activitypub_objects.remote_id`. This
     * is the `$resolveObjectUri` closure `Extend\Federation::fromActivity()` hooks receive, e.g.
     * to follow a reply's `inReplyTo` without `discussions` needing to know this table exists.
     */
    public function resolveObjectUri(string $uri): ?object
    {
        if (parse_url($uri, PHP_URL_HOST) === parse_url((string) config('app.url'), PHP_URL_HOST)) {
            $path = trim(parse_url($uri, PHP_URL_PATH) ?? '', '/');

            if (! preg_match('#^ap/([^/]+)/([^/]+)$#', $path, $matches)) {
                return null;
            }

            $registration = $this->federationFor($matches[1]);

            return $registration ? $registration->model::find($matches[2]) : null;
        }

        return ActivitypubObject::where('remote_id', $uri)->first()?->federatable;
    }

    /**
     * A local Person's canonical AP id is always this instance's own minted `/ap/people/{id}`
     * URI, computed rather than stored (nothing to keep in sync). A remote actor's is whatever
     * `activitypub_actors.remote_id` recorded when it was fetched -- arbitrary per remote
     * server, never reconstructable from anything Kopling-side.
     */
    public function canonicalActorUri(Person $person): string
    {
        if ($person->isRemote()) {
            return $person->activitypubActor?->remote_id
                ?? throw new RuntimeException("Remote person {$person->id} has no activitypub_actors.remote_id.");
        }

        return route('kopling-activitypub::activitypub/people.show', ['person' => $person->id]);
    }

    /**
     * The URL segment `GET /ap/{type}/{id}` matches against -- a kebab-plural of the model's
     * own class basename (`Moment` -> `moments`, `Reply` -> `replies`), never a field the
     * registration itself has to declare, so an `Extend\Federation` entry only ever states facts
     * about the model, never anything route-shaped.
     */
    public function routeSegmentFor(string $modelClass): string
    {
        return Str::plural(Str::kebab(class_basename($modelClass)));
    }

    /**
     * @return Federation|null
     */
    public function federationFor(string $routeSegment)
    {
        return $this->extensions->federatedModels()
            ->first(fn (Federation $registration) => $this->routeSegmentFor($registration->model) === $routeSegment);
    }

    /**
     * @return Federation|null
     */
    public function federationForModel(string $modelClass)
    {
        return $this->extensions->federatedModels()
            ->first(fn (Federation $registration) => $registration->model === $modelClass);
    }

    public function canonicalObjectUri(Federation $registration, object $model): string
    {
        return route('kopling-activitypub::activitypub/objects.show', [
            'type' => $this->routeSegmentFor($registration->model),
            'id' => $model->getKey(),
        ]);
    }

    /**
     * The full outbound ActivityStreams object -- `@context`/`id`/`type`/`attributedTo` filled
     * in generically, merged with whatever `toActivity()` returns (falling back to a bare
     * `content` field from `contentField()` when no closure is registered), then merged again
     * with every `ExtendsFederatedObjects` contribution registered for this exact model class --
     * e.g. an image-gallery extension adding its own `attachment` array to a `Moment` it doesn't
     * own. Contributions are applied last, in registration order, so a later one can deliberately
     * override an earlier one's key the same "last-registered wins" way `Extend\Model::linksTo()`
     * already documents -- but never override the envelope or the owning registration's own
     * fields, since those come from whoever actually owns this model.
     *
     * @return array<string, mixed>
     */
    public function toActivityJson(Federation $registration, object $model): array
    {
        $envelope = [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $this->canonicalObjectUri($registration, $model),
            'type' => $registration->apType,
        ];

        if ($registration->attributedToRelation !== null) {
            $author = $model->{$registration->attributedToRelation};

            if ($author instanceof Person) {
                $envelope['attributedTo'] = $this->canonicalActorUri($author);
            }
        }

        $body = match (true) {
            $registration->toActivity !== null => ($registration->toActivity)($model),
            $registration->contentField !== null => ['content' => $model->{$registration->contentField}],
            default => [],
        };

        $contributions = $this->extensions->federatedObjectContributions()->get($registration->model, []);

        foreach ($contributions as $contribute) {
            $body = array_merge($body, $contribute($model));
        }

        // Envelope merged last -- neither toActivity() nor a contribution can override
        // @context/id/type/attributedTo this way, closing a gap that a plain
        // array_merge($envelope, $body) would otherwise leave open (later array wins on a
        // string-key collision).
        return array_merge($body, $envelope);
    }

    /**
     * Wraps `toActivityJson()`'s object in the `Create` activity envelope outbound delivery
     * actually sends -- a plain `#activity` suffix on the object's own id, not a persisted row
     * of its own (nothing else ever needs to look a `Create` activity back up by id).
     *
     * @return array<string, mixed>
     */
    public function toCreateActivity(Federation $registration, object $model, Person $actor): array
    {
        $object = $this->toActivityJson($registration, $model);

        return [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $object['id'].'/activity',
            'type' => 'Create',
            'actor' => $this->canonicalActorUri($actor),
            'object' => $object,
            'to' => ['https://www.w3.org/ns/activitystreams#Public'],
        ];
    }
}
