<?php

declare(strict_types=1);

namespace Kopling\Reactions;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Kopling\Core\Content\Moment;
use Kopling\Core\Database\Model;
use Kopling\Core\People\Person;

/**
 * A single person's single-emoji reaction to a moment. Mirrors core's own models
 * (`Moment`, `Person`): `HasUuids`, an explicit `$fillable`, plain `belongsTo` relations.
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
        'moment_id',
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

    public function moment(): BelongsTo
    {
        return $this->belongsTo(Moment::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * Everything the rail needs to render for one moment and one viewer: the per-emoji
     * counts, which emoji the viewer has already picked (empty for a guest), and whether
     * they may react at all. Shared by the card-footer component and the toggle route so
     * both render the identical fragment.
     *
     * @return array{counts: array<string, int>, mine: array<int, string>, canReact: bool}
     */
    public static function state(Moment $moment, ?Person $actor): array
    {
        $reactions = static::onMoment($moment);

        return [
            'counts' => $reactions->groupBy('emoji')->map->count()->all(),
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
     * The most recent worded reactions on a moment (newest first) -- the "Latest reactions"
     * strip. Plain (wordless) rail toggles are excluded. Authors ride along via `$with`.
     *
     * @return \Illuminate\Support\Collection<int, static>
     */
    public static function latestWorded(Moment $moment, int $limit = self::WORDS_LIMIT): \Illuminate\Support\Collection
    {
        return static::onMoment($moment)
            ->whereNotNull('word')
            ->sortByDesc('created_at')
            ->take($limit)
            ->values();
    }

    /**
     * One moment's reactions, read from the `reactions` relation the feed eager-loads (see
     * Extension::models) rather than re-querying per card -- on the feed the whole page's
     * reactions arrive in one `whereIn`. Falls back to a query for a single moment that wasn't
     * loaded that way (the toggle re-render, a htmx fragment). Same shared-read pattern as
     * discussions' Reply::statsFor.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, static>
     */
    protected static function onMoment(Moment $moment): \Illuminate\Database\Eloquent\Collection
    {
        // load(), not a bare ->get(): the rail (state) and the strip (latestWorded) both call
        // this for the same moment, so caching the relation on the model means a single-moment
        // re-render (the toggle/word htmx fragment) queries once, not once each.
        if (! $moment->relationLoaded('reactions')) {
            $moment->load('reactions');
        }

        return $moment->getRelation('reactions');
    }
}
