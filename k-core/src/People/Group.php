<?php

declare(strict_types=1);

namespace Kopling\Core\People;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Kopling\Core\Database\Model;
use Kopling\Core\Authorization\Permission;

class Group extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
    ];

    public function people(): BelongsToMany
    {
        return $this->belongsToMany(Person::class);
    }

    /**
     * The raw grants themselves -- e.g. "kopling-core::manage-people" or
     * "kopling-example::do-a-thing" -- as real `Authorization\Permission` rows. A permission's
     * own definition (label/description/etc.) still lives entirely in code; only the grant is
     * a row (see `Authorization\Permission`'s own docblock).
     */
    public function permissions(): HasMany
    {
        return $this->hasMany(Permission::class);
    }

    public function hasPermission(string $id): bool
    {
        return $this->permissions()->where('permission', $id)->exists();
    }

    public function givePermissionTo(string $id): void
    {
        $this->permissions()->firstOrCreate(['permission' => $id]);
    }

    public function revokePermissionTo(string $id): void
    {
        $this->permissions()->where('permission', $id)->delete();
    }
}
