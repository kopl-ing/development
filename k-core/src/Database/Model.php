<?php

declare(strict_types=1);

namespace Kopling\Core\Database;

use Kopling\Core\Database\Concerns\HasExtendedCasts;

/**
 * The base class for any real model with no other base-class constraint of its own -- extend
 * this instead of plain Eloquent `Model` so `Extend\Model::cast()` (and anything else
 * `Extension\Manager::models()` wires up this way in future) actually applies. A model that
 * can't extend this because it already extends something else (`Person`, which must extend
 * `Authenticatable`) uses `HasExtendedCasts` directly instead -- see that trait's own docblock
 * for why both paths end up reading/writing the exact same registry regardless.
 */
class Model extends \Illuminate\Database\Eloquent\Model
{
    use HasExtendedCasts;

    /**
     * Flat, class-keyed cast registry populated once at boot by `Extension\Manager::models()`
     * -- a plain static array `HasExtendedCasts::getCasts()` reads, not a live call back into
     * Manager (or the container) on every attribute access. Declared here rather than on the
     * trait itself, and `public` rather than `protected`, precisely so a class that only uses
     * the trait (never extends this one) still shares this single slot instead of getting its
     * own independent, always-empty copy.
     *
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
