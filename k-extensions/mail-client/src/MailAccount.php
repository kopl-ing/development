<?php

declare(strict_types=1);

namespace Kopling\MailClient;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Kopling\Core\Database\Model;
use Kopling\Core\People\Person;

class MailAccount extends Model
{
    use HasUuids;

    protected $fillable = [
        'person_id',
        'label',
        'email_address',
        'incoming_host',
        'incoming_port',
        'incoming_encryption',
        'outgoing_host',
        'outgoing_port',
        'outgoing_encryption',
        'auth_type',
        'username',
        'password',
        'oauth_provider',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'is_default',
    ];

    protected $hidden = [
        'password',
        'access_token',
        'refresh_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'is_default' => 'boolean',
            'last_synced_at' => 'datetime',
            'syncing_since' => 'datetime',
        ];
    }

    /**
     * Computed rather than a stored enum -- a single source of truth (these three timestamps)
     * beats a status column that could drift out of sync with them.
     */
    public function syncStatus(): string
    {
        if ($this->syncing_since !== null) {
            return 'syncing';
        }

        if ($this->last_sync_error !== null && $this->last_sync_error !== '') {
            return 'failed';
        }

        return $this->last_synced_at !== null ? 'synced' : 'pending';
    }

    /**
     * Only one default "From" account per person -- a conditional uniqueness (per person_id,
     * only among is_default = true rows) a plain unique index can't express, so enforced here
     * instead. Runs on `saved`, not `saving`: HasUuids only assigns the primary key during its
     * own `creating` listener, which fires after `saving` -- `whereKeyNot()` needs that key set.
     */
    protected static function booted(): void
    {
        static::saved(function (MailAccount $account) {
            if ($account->is_default) {
                static::query()
                    ->where('person_id', $account->person_id)
                    ->whereKeyNot($account->id)
                    ->update(['is_default' => false]);
            }
        });
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function folders(): HasMany
    {
        return $this->hasMany(MailFolder::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(MailMessage::class);
    }

    /**
     * The progress bar's denominator -- summed from each folder's own `message_count`, read
     * once per discovery pass off a cheap IMAP STATUS call (see SyncMailAccount), not derived
     * from counting local rows (which would just show sync progress against itself).
     */
    public function totalMessageCount(): int
    {
        return (int) $this->folders()->sum('message_count');
    }

    public function syncedMessageCount(): int
    {
        return $this->messages()->count();
    }
}
