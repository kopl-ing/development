<?php

declare(strict_types=1);

namespace Kopling\Core\People;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

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
     * Checks the raw grant, e.g. "kopling-core::manage-people" or "kopling-example::do-a-thing" --
     * there's no Permission row to relate to, permission definitions live in code.
     */
    public function hasPermission(string $id): bool
    {
        return DB::table('group_permission')
            ->where('group_id', $this->id)
            ->where('permission', $id)
            ->exists();
    }

    public function givePermissionTo(string $id): void
    {
        DB::table('group_permission')->insertOrIgnore([
            'group_id' => $this->id,
            'permission' => $id,
        ]);
    }

    public function revokePermissionTo(string $id): void
    {
        DB::table('group_permission')
            ->where('group_id', $this->id)
            ->where('permission', $id)
            ->delete();
    }
}
