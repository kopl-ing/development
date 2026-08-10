<?php

declare(strict_types=1);

namespace Kopling\Core\People;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Kopling\Core\Content\Moment;
use Kopling\Core\Database\Concerns\HasExtendedCasts;

/**
 * Extends `Authenticatable` (Laravel's own auth-user base), not `Kopling\Core\Database\Model`
 * -- PHP single inheritance means it can't do both, so it `use`s `HasExtendedCasts` directly
 * instead, the same registry every other real model reads via `Database\Model`.
 */
class Person extends Authenticatable
{
    use HasExtendedCasts;
    use HasUuids;
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'origin',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'communication_blocked_at' => 'datetime',
            'access_blocked_at' => 'datetime',
            'access_blocked_until' => 'datetime',
        ];
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class);
    }

    /**
     * True if any of this person's groups has been granted this permission. This is the
     * base grant check every registered Gate ability runs first -- a Permission's optional
     * callback (see Kopling\Core\Extend\Permission) only ever narrows this further,
     * never replaces it.
     */
    public function hasPermission(string $id): bool
    {
        return DB::table('group_permission')
            ->join('group_person', 'group_person.group_id', '=', 'group_permission.group_id')
            ->where('group_person.person_id', $this->id)
            ->where('group_permission.permission', $id)
            ->exists();
    }

    /**
     * A deterministic per-identity accent color for an avatar -- the same seed (a Person's id,
     * or a bare name for a reactor with no resolvable Person) always produces the same hue, so
     * one person reads as the same color everywhere they show up.
     */
    public static function colorFor(string $seed): string
    {
        return 'hsl('.(crc32($seed) % 360).'deg 45% 45%)';
    }

    public function avatarColor(): string
    {
        return static::colorFor($this->id);
    }

    public function moments(): HasMany
    {
        return $this->hasMany(Moment::class);
    }

    public function identities(): HasMany
    {
        return $this->hasMany(PersonIdentity::class);
    }

    public function isLocal(): bool
    {
        return $this->origin === null;
    }

    public function isRemote(): bool
    {
        return ! $this->isLocal();
    }

    /**
     * `communication_blocked_at !== null` and `visibility === 'hidden'` are plain single-column
     * checks callers read directly, no wrapper needed -- this is the one sanction-state check
     * that earns a method, since it's real logic (comparing two columns against `now()`, not a
     * null-check): `access_blocked_at` set + `access_blocked_until` null means a permanent ban;
     * set + a past `access_blocked_until` means an expired, no-longer-active temporary suspend.
     */
    public function isAccessBlocked(): bool
    {
        return $this->access_blocked_at !== null
            && ($this->access_blocked_until === null || $this->access_blocked_until->isFuture());
    }
}
