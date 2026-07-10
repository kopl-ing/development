<?php

declare(strict_types=1);

namespace Kopling\Reactions;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Kopling\Core\Content\Moment;
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

    protected $fillable = [
        'moment_id',
        'person_id',
        'emoji',
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
     * Everything the rail needs to render for one moment and one viewer: the per-emoji
     * counts, which emoji the viewer has already picked (empty for a guest), and whether
     * they may react at all. Shared by the card-footer component and the toggle route so
     * both render the identical fragment.
     *
     * @return array{counts: array<string, int>, mine: array<int, string>, canReact: bool}
     */
    public static function state(Moment $moment, ?Person $actor): array
    {
        $counts = static::query()
            ->where('moment_id', $moment->id)
            ->selectRaw('emoji, count(*) as aggregate')
            ->groupBy('emoji')
            ->pluck('aggregate', 'emoji')
            ->all();

        $mine = $actor
            ? static::query()
                ->where('moment_id', $moment->id)
                ->where('person_id', $actor->id)
                ->pluck('emoji')
                ->all()
            : [];

        return [
            'counts' => $counts,
            'mine' => $mine,
            'canReact' => $actor !== null,
        ];
    }
}
