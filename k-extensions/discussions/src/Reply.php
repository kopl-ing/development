<?php

declare(strict_types=1);

namespace Kopling\Discussions;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Kopling\Core\Content\Moment;
use Kopling\Core\People\Person;

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
     * @return array{count: int, people: int, words: int}
     */
    public static function statsFor(Moment $moment): array
    {
        $replies = static::query()
            ->where('moment_id', $moment->id)
            ->get(['person_id', 'body']);

        return [
            'count' => $replies->count(),
            'people' => $replies->pluck('person_id')->unique()->count(),
            'words' => $replies->sum(fn (self $reply) => str_word_count((string) $reply->body)),
        ];
    }
}
