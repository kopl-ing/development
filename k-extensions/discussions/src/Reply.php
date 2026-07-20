<?php

declare(strict_types=1);

namespace Kopling\Discussions;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection as SupportCollection;
use Kopling\Core\Content\Moment;
use Kopling\Core\Database\Model;
use Kopling\Core\People\Person;
use Kopling\Core\Ux\Editor\PlainTextExtractor;

/**
 * One reply to a moment. Defined entirely on the extension side (relations included) so the
 * discussion feature never reaches into core's `Moment`.
 */
class Reply extends Model
{
    use HasUuids;

    protected $fillable = [
        'moment_id',
        'person_id',
        'body',
        'body_html',
    ];

    /**
     * A reply renders through the exact same extensible `Top`/`Badges`/`Body`/`Footer` mechanism
     * a Moment's own card does (`<x-k::card.card>`'s `$topSlot`/`$badgesSlot`/`$bodySlot`/
     * `$footerSlot`), just its own slot family -- never Core's `Card\Top::SLOT`/`Badges::SLOT`/
     * `Body::SLOT`/`Footer::SLOT` directly. A `Reply` isn't a `Moment`: sharing the same global
     * slot names would mean every Moment-only registration (reactions' vote/rail/words, tags'
     * own badge row, this same extension's own teaser/engage/quote-op) renders on a reply too,
     * with nothing about those concepts applying to one. `BADGES_SLOT` has no registrations
     * today -- kept for the same reason the other three are always passed rather than left to
     * default to Core's own constants: so a future Core registration into its own
     * `Card\Badges::SLOT` (mirroring how tags targets it for Moments) can never silently bleed
     * onto a reply's card just because this one slot was the one left unscoped.
     */
    public const TOP_SLOT = 'kopling-discussions::reply.top';

    public const BADGES_SLOT = 'kopling-discussions::reply.badges';

    public const BODY_SLOT = 'kopling-discussions::reply.body';

    public const FOOTER_SLOT = 'kopling-discussions::reply.footer';

    public function moment(): BelongsTo
    {
        return $this->belongsTo(Moment::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * A moment's replies oldest-first (reading order), authors eager-loaded.
     *
     * @return Collection<int, static>
     */
    public static function forMoment(Moment $moment): Collection
    {
        return static::query()
            ->with('person')
            ->where('moment_id', $moment->id)
            ->oldest()
            ->get();
    }

    /**
     * What the activity teaser says: how many replies, how many distinct people, and how many
     * words they spent -- the demo's "N people used X words to talk about this".
     *
     * Memoized per-moment for the lifetime of the request: the card renders this for two
     * separate slots (the body `teaser` and the footer `engage`), which otherwise each fire
     * their own query -- so a feed of N cards ran 2N reply queries (see issue #4). The static
     * cache collapses that to one per moment; it's a request-scoped process that never spans
     * requests, so there's nothing to invalidate.
     *
     * @return array{count: int, people: int, words: int}
     */
    public static function statsFor(Moment $moment): array
    {
        // Read the `replies` relation the discussions extension eager-loads onto every Moment
        // (see Extension::models()) rather than querying per card: on the feed the whole page's
        // replies arrive in one `whereIn`, and `->getRelation()` never lazy-loads if it wasn't.
        $replies = $moment->relationLoaded('replies')
            ? $moment->getRelation('replies')
            : $moment->replies()->get(['moment_id', 'person_id', 'body']);

        return [
            'count' => $replies->count(),
            'people' => $replies->pluck('person_id')->unique()->count(),
            'words' => $replies->sum(fn (self $reply) => str_word_count(PlainTextExtractor::extract((string) $reply->body))),
        ];
    }

    /**
     * Up to `$limit` distinct people who've replied to `$moment`, most-recent-reply-first -- the
     * teaser's avatar row ("faces" are the fastest, pre-linguistic belonging signal a newcomer
     * scans for). A dedicated query, not reused from `statsFor()`'s already-loaded collection:
     * `statsFor()` only ever needs `person_id` for uniqueness counting, never the full `person`
     * row, so eager-loading it there for every card in a feed would cost every reader for a
     * signal only the teaser actually renders. Memoized per-moment per-request, same reasoning
     * `statsFor()` already documents -- both are called once per card from separate slots.
     *
     * @return SupportCollection<int, Person>
     */
    public static function recentContributors(Moment $moment, int $limit = 5): SupportCollection
    {
        static $cache = [];

        return $cache[$moment->getKey()] ??= static::query()
            ->where('moment_id', $moment->id)
            ->with('person')
            ->latest()
            ->get()
            ->unique('person_id')
            ->take($limit)
            ->pluck('person')
            ->filter()
            ->values();
    }
}
