<?php

declare(strict_types=1);

namespace Kopling\Core\Extend;

use Closure;

/**
 * One model's worth of federation declaration -- the fluent entry point
 * `HasFederatedModels::federatedModels()` returns instances of. Declares the shape of a
 * federatable model without depending on any federation protocol itself; `k-extensions/activitypub`
 * reads these to serialize outbound and resolve inbound, the same way `ValidatesModels` is read
 * today.
 */
class Federation
{
    public ?string $apType = null;

    public ?string $contentField = null;

    public ?string $attributedToRelation = null;

    public ?Closure $toActivity = null;

    public ?Closure $fromActivity = null;

    public function __construct(public readonly string $model)
    {
    }

    /**
     * The ActivityStreams object type this model serializes as, e.g. `'Note'`, `'Article'`.
     */
    public function apType(string $apType): self
    {
        $this->apType = $apType;

        return $this;
    }

    /**
     * The attribute `toActivity()`'s default rendering (when no closure is registered) reads as
     * this object's `content`.
     */
    public function contentField(string $contentField): self
    {
        $this->contentField = $contentField;

        return $this;
    }

    /**
     * The relation name resolving this object's author `Person` -- read for the outbound
     * `attributedTo` field and for permission/ownership checks, never guessed from a column name.
     */
    public function attributedToRelation(string $attributedToRelation): self
    {
        $this->attributedToRelation = $attributedToRelation;

        return $this;
    }

    /**
     * Outbound serialization hook: `Closure(object $model): array` -- returns this object's own
     * ActivityStreams fields (never `@context`/`id`/`type`/`attributedTo`, which the generic
     * `/ap/{type}/{id}` controller fills in from `apType()`/`attributedToRelation()` and the
     * canonical URI itself). Optional -- when absent, the controller renders `contentField()`
     * alone.
     */
    public function toActivity(Closure $callback): self
    {
        $this->toActivity = $callback;

        return $this;
    }

    /**
     * Inbound ingestion hook: `Closure(array $activity, \Kopling\Core\People\Person $author,
     * \Closure $resolveObjectUri): ?object` -- given a decoded ActivityStreams object and its
     * already-resolved local author `Person`, returns the local row it maps to (creating one if
     * this is the first time it's been seen), or `null` to drop the activity (e.g. it references
     * something that can't be resolved). `$resolveObjectUri` is `Closure(string $uri): ?object`
     * -- resolves any AP object URI (this instance's own, or a previously-seen remote one) back
     * to whatever local model it maps to, for following a field like `inReplyTo` without this
     * model's own package needing to know how that resolution actually works (owned by
     * `activitypub`, injected here so core's own contract stays protocol-agnostic).
     */
    public function fromActivity(Closure $callback): self
    {
        $this->fromActivity = $callback;

        return $this;
    }
}
