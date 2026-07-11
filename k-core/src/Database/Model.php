<?php

declare(strict_types=1);

namespace Kopling\Core\Database;

class Model extends \Illuminate\Database\Eloquent\Model
{
    /**
     * Flat, class-keyed cast registry populated once at boot by `Extension\Manager::models()`
     * -- a plain static array `getCasts()` below reads, not a live call back into Manager (or
     * the container) on every attribute access.
     *
     * @var array<class-string, array<string, string>>
     */
    protected static array $extendedCasts = [];

    /**
     * @param  array<class-string, array<string, string>>  $casts
     */
    public static function registerCasts(array $casts): void
    {
        static::$extendedCasts = $casts;
    }

    /**
     * Core's own `$casts` always wins over whatever an extension declared via
     * `Extend\Model::cast()` -- an extension can only fill in a cast core hasn't already
     * claimed for that attribute, never override one. `parent::getCasts()` is merged in last
     * so its keys win the collision.
     */
    public function getCasts(): array
    {
        return array_merge(static::$extendedCasts[static::class] ?? [], parent::getCasts());
    }
}
