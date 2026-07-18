<?php

declare(strict_types=1);

namespace Kopling\Discussions;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
}
