<?php

declare(strict_types=1);

namespace Kopling\Core\Storage;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Kopling\Core\Database\Model;

/**
 * An admin-registered storage backend -- named "drive", not "filesystem", matching
 * `.docs/planning/decisions.md`'s own wording ("the request->drive resolver") and avoiding a
 * class-name collision with `Illuminate\Contracts\Filesystem\Filesystem`, the type `Resolver`
 * itself returns.
 */
class Drive extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'driver',
        'settings',
        'supports_public',
        'supports_signed',
        'writable',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'supports_public' => 'boolean',
            'supports_signed' => 'boolean',
            'writable' => 'boolean',
            'enabled' => 'boolean',
        ];
    }

    public function mappings(): HasMany
    {
        return $this->hasMany(StorageMapping::class);
    }
}
