<?php

declare(strict_types=1);

namespace Kopling\Tags;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Kopling\Core\Content\Moment;
use Kopling\Core\Database\Model;

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
    ];

    public function moments(): BelongsToMany
    {
        return $this->belongsToMany(Moment::class, 'moment_tag');
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
}
