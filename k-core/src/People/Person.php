<?php

declare(strict_types=1);

namespace Kopling\Core\People;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
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
}
