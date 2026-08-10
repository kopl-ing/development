<?php

declare(strict_types=1);

namespace Kopling\Activitypub;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Kopling\Core\Database\Model;
use Kopling\Core\People\Person;

/**
 * One outbound delivery attempt, persisted -- see the migration's own docblock for why this
 * exists at all rather than trusting the queued job's in-memory payload alone.
 */
class ActivitypubDelivery extends Model
{
    use HasUuids;

    protected $fillable = [
        'person_id',
        'inbox_url',
        'activity',
        'attempts',
        'last_error',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'activity' => 'array',
            'delivered_at' => 'datetime',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function isDelivered(): bool
    {
        return $this->delivered_at !== null;
    }
}
