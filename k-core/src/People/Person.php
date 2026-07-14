<?php

declare(strict_types=1);

namespace Kopling\Core\People;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

class Person extends Authenticatable
{
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
