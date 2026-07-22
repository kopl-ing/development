<?php

declare(strict_types=1);

namespace Kopling\Core\Storage;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Kopling\Core\Database\Model;

/**
 * The purpose->drive assignment -- `$request_id` (the already-prefixed `StorageRequest` id) is
 * the primary key itself, not a surrogate uuid, since one purpose maps to exactly one drive and
 * the id is already globally unique by construction. A row simply not existing is what
 * "unmapped" means -- see `Resolver`.
 */
class StorageMapping extends Model
{
    protected $primaryKey = 'request_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'request_id',
        'drive_id',
        'prefix',
    ];

    public function drive(): BelongsTo
    {
        return $this->belongsTo(Drive::class);
    }
}
