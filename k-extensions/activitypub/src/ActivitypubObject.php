<?php

declare(strict_types=1);

namespace Kopling\Activitypub;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Kopling\Core\Database\Model;

/**
 * Polymorphic, one row per federated non-Person object (Moment, Reply, ...) -- every
 * `Extend\Federation` registration's `remote_id`/`federated_at` lives here, never on the
 * federatable model's own table. See decisions.md, 2026-08-10.
 */
class ActivitypubObject extends Model
{
    use HasUuids;

    protected $fillable = [
        'federatable_type',
        'federatable_id',
        'remote_id',
        'federated_at',
    ];

    protected function casts(): array
    {
        return [
            'federated_at' => 'datetime',
        ];
    }

    public function federatable(): MorphTo
    {
        return $this->morphTo();
    }
}
