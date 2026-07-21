<?php

declare(strict_types=1);

namespace Kopling\Core\Database;

use Kopling\Core\Database\Concerns\HasExtendedCasts;

/**
 * The base class for any real model with no other base-class constraint -- extend this instead
 * of plain Eloquent `Model` so `Extend\Model::cast()` applies. A model that can't (`Person`,
 * which must extend `Authenticatable`) uses `HasExtendedCasts` directly instead.
 */
class Model extends \Illuminate\Database\Eloquent\Model
{
    use HasExtendedCasts;

    /**
     * @var array<class-string, array<string, string>>
     */
    public static array $extendedCasts = [];

    /**
     * @param  array<class-string, array<string, string>>  $casts
     */
    public static function registerCasts(array $casts): void
    {
        static::$extendedCasts = $casts;
    }
}
