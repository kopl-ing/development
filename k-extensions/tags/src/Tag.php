<?php

declare(strict_types=1);

namespace Kopling\Tags;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Kopling\Core\Content\Moment;

/**
 * A tag. Mirrors core's own models (`HasUuids`, explicit `$fillable`); the `moments()`
 * relation is defined here rather than on `Moment` so the extension never has to reach into
 * a core model to add its own concern.
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
     * The tags on one moment, alphabetical -- read via the pivot so nothing needs adding to
     * `Moment` itself.
     *
     * @return Collection<int, static>
     */
    public static function forMoment(Moment $moment): Collection
    {
        return static::query()
            ->whereHas('moments', fn ($query) => $query->whereKey($moment->id))
            ->orderBy('name')
            ->get();
    }
}
