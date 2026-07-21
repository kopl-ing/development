<?php

declare(strict_types=1);

namespace Kopling\Core\Database\Concerns;

use Kopling\Core\Database\Model;

/**
 * `Database\Model`'s `getCasts()` override, extracted so a model that can't extend
 * `Database\Model` (`Person`, which must extend `Authenticatable`) still picks up
 * `Extend\Model::cast()` extensions via `use`. Always reads/writes through
 * `Database\Model::$extendedCasts` explicitly, never `static::` -- PHP gives each trait-consuming
 * class its own independent copy of a property the trait itself declares, so declaring no static
 * property here is what keeps every consumer sharing one registry.
 */
trait HasExtendedCasts
{
    /**
     * Core's own `$casts` always wins over an `Extend\Model::cast()` extension for the same
     * attribute -- `parent::getCasts()` merges in last so its keys win the collision.
     */
    public function getCasts(): array
    {
        return array_merge(Model::$extendedCasts[static::class] ?? [], parent::getCasts());
    }
}
