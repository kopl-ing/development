<?php

declare(strict_types=1);

namespace Kopling\Core\Content;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Kopling\Core\Database\Model;
use Kopling\Core\People\Person;

class Moment extends Model
{
    use HasUuids;

    protected $perPage = 20;

    protected $fillable = [
        'person_id',
        'title',
        'body',
        'body_html',
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * An unsaved Moment standing in for "the thing being composed" -- lets the composer reuse
     * Person\Avatar/Card\Author unchanged (both read the `person` relation, which `setRelation()`
     * satisfies without a real `person_id`), rather than needing composer-specific leaves. Never
     * pass this to anything that calls `Context::getSubjectUrl()` (Title, the full `Card`
     * wrapper) -- `linksTo()`'s route needs a real `getRouteKey()`, which this doesn't have.
     */
    public static function draft(): self
    {
        return tap(new self(), fn (self $moment) => $moment->setRelation('person', auth()->user()));
    }
}
