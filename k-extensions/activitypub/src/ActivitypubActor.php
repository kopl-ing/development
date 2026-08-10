<?php

declare(strict_types=1);

namespace Kopling\Activitypub;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Kopling\Core\Database\Model;
use Kopling\Core\People\Person;

/**
 * One-to-one with `people` -- a row existing here (regardless of `Person::$origin`) is what
 * makes a Person an AP actor. See decisions.md, 2026-08-10.
 */
class ActivitypubActor extends Model
{
    use HasUuids;

    /**
     * Mirrors the migration's own `->default(true)` -- without this, a freshly `create()`d
     * instance reads `federation_enabled` as `null` in-memory until reloaded from the database
     * (Eloquent never re-fetches a column it wasn't given, DB-level default or not), which would
     * make `isFederating()` wrongly answer `false` for the same request that just set a handle.
     */
    protected $attributes = [
        'federation_enabled' => true,
    ];

    protected $fillable = [
        'person_id',
        'handle',
        'federation_enabled',
        'remote_id',
        'inbox_url',
        'outbox_url',
        'shared_inbox_url',
        'public_key',
        'private_key',
        'fetched_at',
    ];

    protected $hidden = [
        'private_key',
    ];

    protected function casts(): array
    {
        return [
            'federation_enabled' => 'boolean',
            'fetched_at' => 'datetime',
        ];
    }

    /**
     * A local Person federates once they've both chosen a handle and not since disabled it --
     * a remote actor's own row (identified by `remote_id`, never `handle`) has no meaningful
     * answer to this, but is never asked either.
     */
    public function isFederating(): bool
    {
        return $this->handle !== null && $this->federation_enabled;
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    protected static function booted(): void
    {
        // Generates a local actor's key pair once, regardless of what creates this row --
        // there's currently no in-app settings page to save a handle from (a manual/tinker step
        // today), so this can't hang off a form-save event; a remote actor's row is never
        // created without its own public_key already known (see Federation\Manager::
        // resolveActor(), Phase 5), so this never fires for one.
        static::creating(function (self $actor) {
            if ($actor->remote_id !== null || $actor->private_key !== null) {
                return;
            }

            $resource = openssl_pkey_new([
                'private_key_bits' => 2048,
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
            ]);

            openssl_pkey_export($resource, $privateKey);

            $actor->private_key = $privateKey;
            $actor->public_key = openssl_pkey_get_details($resource)['key'];
        });
    }
}
