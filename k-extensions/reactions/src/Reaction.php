<?php

declare(strict_types=1);

namespace Kopling\Reactions;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Kopling\Core\Content\Moment;
use Kopling\Core\Database\Model;
use Kopling\Core\People\Person;

/**
 * A single person's single-emoji reaction to a reactable -- a Moment today, a Reply too once
 * `k-extensions/discussions` is installed (see `Extension::models()`'s own `->morphAlias()`
 * calls). Mirrors core's own models (`Moment`, `Person`): `HasUuids`, an explicit `$fillable`,
 * plain relations -- `reactable` is `morphTo()` rather than a fixed `belongsTo(Moment::class)`,
 * which is what makes a second reactable type possible without a second `reactions`-shaped
 * table.
 */
class Reaction extends Model
{
    use HasUuids;

    /**
     * The emoji a person may react with. Deliberately small and curated -- a fixed palette
     * (rather than a free emoji picker) keeps the rail a calm aggregate, and lets the toggle
     * route validate against a known set instead of accepting arbitrary input.
     */
    public const PALETTE = ['👍', '❤️', '😂', '🎉', '😮', '😢'];

    /** How many worded reactions the "Latest reactions" strip shows before it stops. */
    public const WORDS_LIMIT = 6;

    /** Longest word a reaction may carry -- matches the migration column + the route guard. */
    public const WORD_MAX = 40;

    protected $fillable = [
        'reactable_type',
        'reactable_id',
        'person_id',
        'emoji',
        'word',
    ];

    /**
     * Always carry the author: the "Latest reactions" strip renders `$reaction->person->name`
     * in the feed, so when the feed eager-loads a moment's `reactions` (see Extension::models)
     * this nests the people into that same batch -- one `whereIn` for the whole page instead of
     * one per worded reaction. The rail (counts only) never reads it; the single tiny over-read
     * on the toggle re-render is well worth avoiding an N+1 across the feed.
     */
    protected $with = ['person'];

    public function reactable(): MorphTo
    {
        return $this->morphTo();
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * Everything the rail needs to render for one reactable and one viewer: the per-emoji
     * counts, which emoji the viewer has already picked (empty for a guest), and whether
     * they may react at all. Shared by the card-footer component and the toggle route so
     * both render the identical fragment.
     *
     * `counts` only tallies *wordless* reactions -- a worded one already renders as its own
     * chip in `words.blade.php` (merged into the same row, see `Card` footer's own decisions.md
     * entry), so counting it here too would show the exact same single row twice: once as a
     * plain "N" badge, once as a chip. `mine` deliberately stays unfiltered (every emoji the
     * viewer has *any* reaction for, worded or not) -- `rail.blade.php` uses it to keep a
     * worded-only reaction's button rendered (just without a redundant count), so clicking it
     * still finds and removes that row via the toggle route, which never distinguishes worded
     * from wordless either.
     *
     * @return array{counts: array<string, int>, mine: array<int, string>, canReact: bool}
     */
    public static function state(Model $reactable, ?Person $actor): array
    {
        $reactions = static::onReactable($reactable);

        return [
            'counts' => $reactions->whereNull('word')->groupBy('emoji')->map->count()->all(),
            'mine' => $actor
                ? $reactions->where('person_id', $actor->id)->pluck('emoji')->values()->all()
                : [],
            'canReact' => $actor !== null,
        ];
    }

    /**
     * The distinct (direction, emoji) pairs a moment's tags configure for voting -- e.g.
     * `[['direction' => 'up', 'emoji' => '👍']]`. Empty when the tags extension isn't
     * installed or none of the moment's tags configure voting. Soft-dependent on
     * `Kopling\Tags\Tag` (guarded by `class_exists`), same convention `widgets`' "popular
     * tags" widget already uses -- reactions never requires tags. Shared by the vote route
     * (validates a submission against it) and the `vote` component (renders a button per
     * configured direction); the rail also reads it to exclude these emoji from its own
     * generic `PALETTE` loop.
     *
     * Moment-only, deliberately never generalized to the polymorphic `reactable` the rest of
     * this class now accepts -- voting is configured per *tag*, and only a Moment carries tags,
     * so a Reply has no equivalent concept to look up (`rail.blade.php` guards its own call to
     * this with `instanceof Moment` for exactly that reason; `vote` itself is simply never
     * registered into `Reply::FOOTER_SLOT` at all -- see `Extension::ux()`).
     *
     * Every 'up' pair sorts before every 'down' pair, regardless of how many tags a moment
     * carries or which order they were attached in -- the `vote` component always shows
     * upvote(s) first, downvote(s) second (or first, if the moment carries no upvote emoji at
     * all), a stable position the design deliberately wants to be predictable card to card.
     *
     * @return array<int, array{direction: 'up'|'down', emoji: string}>
     */
    public static function voteConfigFor(Moment $moment): array
    {
        if (! class_exists(\Kopling\Tags\Tag::class)) {
            return [];
        }

        $pairs = [];

        foreach (\Kopling\Tags\Tag::forMoment($moment) as $tag) {
            foreach (['up' => $tag->upvote_emoji, 'down' => $tag->downvote_emoji] as $direction => $emoji) {
                if ($emoji === null) {
                    continue;
                }

                $pair = ['direction' => $direction, 'emoji' => $emoji];

                if (! in_array($pair, $pairs, true)) {
                    $pairs[] = $pair;
                }
            }
        }

        $isUp = fn (array $pair): bool => $pair['direction'] === 'up';

        return [
            ...array_values(array_filter($pairs, $isUp)),
            ...array_values(array_filter($pairs, fn (array $pair) => ! $isUp($pair))),
        ];
    }

    /**
     * The most recent worded reactions on a reactable (newest first) -- the "Latest reactions"
     * strip. Plain (wordless) rail toggles are excluded. Authors ride along via `$with`.
     *
     * @return \Illuminate\Support\Collection<int, static>
     */
    public static function latestWorded(Model $reactable, int $limit = self::WORDS_LIMIT): \Illuminate\Support\Collection
    {
        return static::onReactable($reactable)
            ->whereNotNull('word')
            ->sortByDesc('created_at')
            ->take($limit)
            ->values();
    }

    /**
     * Resolves a `{type}/{id}` route pair (the generic toggle/word routes -- see `routes/
     * web.php`) into the real reactable model. `$type` must already be a registered morph-map
     * alias (`Extension::models()`'s own `->morphAlias()` calls) -- deliberately never falls
     * back to treating `$type` as a raw class name, which would let a request instantiate and
     * query *any* Eloquent model in the app by name (an IDOR-by-class-name hole), not just the
     * reactable types this extension actually knows about.
     */
    public static function resolveReactable(string $type, string $id): Model
    {
        $class = Relation::getMorphedModel($type);

        abort_unless($class !== null && class_exists($class), 404);

        /** @var Model $reactable */
        $reactable = $class::findOrFail($id);

        return $reactable;
    }

    /**
     * One reactable's reactions, read from the `reactions` relation the feed eager-loads (see
     * Extension::models) rather than re-querying per card -- on the feed the whole page's
     * reactions arrive in one `whereIn`. Falls back to a query for a single reactable that
     * wasn't loaded that way (the toggle/word htmx fragment). Same shared-read pattern as
     * discussions' Reply::statsFor.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, static>
     */
    protected static function onReactable(Model $reactable): \Illuminate\Database\Eloquent\Collection
    {
        // load(), not a bare ->get(): the rail (state) and the strip (latestWorded) both call
        // this for the same reactable, so caching the relation on the model means a
        // single-reactable re-render (the toggle/word htmx fragment) queries once, not once each.
        if (! $reactable->relationLoaded('reactions')) {
            $reactable->load('reactions');
        }

        return $reactable->getRelation('reactions');
    }
}
