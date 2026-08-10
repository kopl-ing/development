<?php

declare(strict_types=1);

namespace Kopling\Activitypub;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Kopling\Core\Database\Model;
use Kopling\Core\People\Person;

/**
 * This extension's own bookkeeping for the AP follow handshake -- a remote actor following a
 * local Person. Not a general social graph; see the federation plan's "Scope for v1".
 */
class ActivitypubFollow extends Model
{
    use HasUuids;

    public const STATE_PENDING = 'pending';

    public const STATE_ACCEPTED = 'accepted';

    protected $fillable = [
        'follower_uri',
        'following_person_id',
        'state',
    ];

    public function followingPerson(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'following_person_id');
    }
}
