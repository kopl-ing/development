<?php

declare(strict_types=1);

namespace Kopling\Core\Database\Concerns;

use Kopling\Core\Database\Model;

/**
 * `Database\Model`'s `getCasts()` override, extracted into a trait so a model that can't
 * extend `Database\Model` for its own reasons -- `Person`, which must extend `Authenticatable`
 * -- still picks up `Extend\Model::cast()` extensions. `use` this directly instead.
 *
 * Always reads/writes through `Database\Model::$extendedCasts` explicitly (never `static::`),
 * so every consumer -- a `Database\Model` subclass or a direct trait user like `Person` alike --
 * shares the exact same single registry `Extension\Manager::models()` populates once, keyed by
 * the consuming model's own class name. There is deliberately no static property declared in
 * this trait itself: PHP gives each trait-*consuming* class its own independent copy of a
 * property the trait declares, so two unrelated classes both using this trait would otherwise
 * each read/write their own empty registry instead of the one Manager actually populated.
 */
trait HasExtendedCasts
{
    /**
     * Core's own `$casts`/`casts()` always wins over whatever an extension declared via
     * `Extend\Model::cast()` -- an extension can only fill in a cast core hasn't already
     * claimed for that attribute, never override one. `parent::getCasts()` is merged in last
     * so its keys win the collision.
     */
    public function getCasts(): array
    {
        return array_merge(Model::$extendedCasts[static::class] ?? [], parent::getCasts());
    }
}
