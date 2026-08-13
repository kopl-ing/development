<?php

declare(strict_types=1);

namespace Kopling\Tags;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Kopling\Core\Content\Moment;
use Kopling\Core\Database\Model;
use Kopling\Core\People\Group;
use Kopling\Core\People\Person;

/**
 * A tag. Mirrors core's own models (`HasUuids`, explicit `$fillable`); the `moments()`
 * relation is defined here rather than on `Moment` so the extension never has to reach into
 * a core model to add its own concern.
 *
 * `$fillable` deliberately never lists `upvote_emoji`/`downvote_emoji` -- real columns on this
 * table (added by `reactions`' own migration), but entirely reactions' concept, not this
 * class's. `TagsController` persists them via `forceCreate()`/`forceFill()` instead of mass
 * assignment, so this model's own fillable list stays scoped to fields it actually considers
 * its own. See decisions.md, 2026-07-18.
 */
class Tag extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'slug',
        'color',
        'icon',
        'description',
        'restricted',
    ];

    protected function casts(): array
    {
        return ['restricted' => 'boolean'];
    }

    public function moments(): BelongsToMany
    {
        return $this->belongsToMany(Moment::class, 'moment_tag');
    }

    /**
     * Which Groups may post into this tag when `restricted` is true -- see `isPostableBy()`.
     * Mirrors Pin's own `groups()` (`group_pin`) exactly, just the other direction (Pin uses it
     * to gate visibility, this gates posting).
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class);
    }

    /**
     * Whether $person may attach this tag to a new moment -- the flat `kopling-tags::post-in-tag`
     * baseline (granted to everyone by default) narrowed further by this tag's own
     * `restricted`+`groups`, same shape as `Pin::isVisibleTo()`. Consulted from `Extension::
     * models()`'s `authorize('create', ...)` hook on `Moment`, and from the tag-search endpoint
     * so a person is never even offered a tag they'd be rejected for on submit. A thin wrapper
     * over `isAllowedBy()` fixed to this tag's own creation-time permission.
     */
    public function isPostableBy(?Person $person): bool
    {
        return $this->isAllowedBy($person, 'kopling-tags::post-in-tag');
    }

    /**
     * The general form `isPostableBy()` wraps -- $permission is always one of *this extension's
     * own* declared permission ids (see `Extension::permissions()`), never anything belonging to
     * whichever other extension's ability triggered the check. `Extension::models()`'s `reply`
     * hook on `Moment` calls this directly with `kopling-tags::reply-in-tag` instead of getting
     * its own `isReplyableBy()`-style method -- tags doesn't need a method named after another
     * extension's verb just because one of its own permissions happens to describe when it
     * applies; it needs exactly one generic predicate ("is $person allowed under this permission
     * of mine"), parameterized by which of its own permissions is in play.
     */
    public function isAllowedBy(?Person $person, string $permission): bool
    {
        if (! Gate::forUser($person)->allows($permission)) {
            return false;
        }

        if (! $this->restricted) {
            return true;
        }

        return $person !== null
            && $person->groups->pluck('id')->intersect($this->groups->pluck('id'))->isNotEmpty();
    }

    /**
     * The tags on one moment, alphabetical. Read from the `tags` relation the feed eager-loads
     * onto every Moment (see Extension::models) rather than a per-card `whereHas` -- on the feed
     * the whole page's tags arrive in one batch. Falls back to a query for a single moment that
     * wasn't loaded that way (e.g. the tag page's own cards). Same shared-read pattern as
     * discussions' Reply::statsFor.
     *
     * @return Collection<int, static>
     */
    public static function forMoment(Moment $moment): Collection
    {
        if (! $moment->relationLoaded('tags')) {
            $moment->load('tags');
        }

        return $moment->getRelation('tags')->sortBy('name')->values();
    }

    /**
     * The most recent activity under this tag -- the newest of its own moments' `created_at`,
     * or (with `discussions` installed) the newest reply to any of its moments, whichever is
     * later. Powers the related-tags rail's recency stamp: a timestamp proves life, a count
     * only proves size. Always at least the tag's own newest moment, since a tag with any
     * moment at all has *some* activity to report. `class_exists()`-guarded reach into
     * `discussions`' `Reply`, same soft-integration convention `widgets`' pulse card already
     * uses for the same class -- not a new cross-extension coupling.
     */
    public function latestActivity(): ?Carbon
    {
        $latest = $this->moments()->max('moments.created_at');

        if (class_exists(\Kopling\Discussions\Reply::class)) {
            $latestReply = \Kopling\Discussions\Reply::query()
                ->whereIn('moment_id', $this->moments()->pluck('moments.id'))
                ->max('created_at');

            if ($latestReply !== null && ($latest === null || $latestReply > $latest)) {
                $latest = $latestReply;
            }
        }

        return $latest ? Carbon::parse($latest) : null;
    }
}
