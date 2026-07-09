<?php

declare(strict_types=1);

namespace Kopling\Core\Extension;

/**
 * What every extension's own `Extension` class extends. Never named `Extension` itself --
 * an extension file doing `use Kopling\Core\Extension\Extension; class Extension extends
 * Extension {}` would collide two symbols of the same name in one file, forcing an import
 * alias every author would have to remember. Deliberately not a Laravel ServiceProvider:
 * extensions declare capabilities here (name/description, and whichever
 * Kopling\Core\Extension\Contract\* interfaces they implement), the actual Laravel wiring
 * (loadViewsFrom, loadMigrationsFrom, etc.) happens inside Kopling\Core\Extension\Manager,
 * never inside code an extension author writes.
 */
abstract class AbstractExtension
{
    abstract public static function name(): string;

    abstract public static function description(): string;
}
