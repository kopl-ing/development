<?php

declare(strict_types=1);

namespace Tests\Fixtures\Extensions\Disableable;

use Kopling\Core\Extension\AbstractExtension;

/**
 * A plain extension, no `CannotBeDisabled` -- for testing that `Manager::extensions()` filters
 * it out once disabled, while `extensions(includeDisabled: true)` still returns it.
 */
class Extension extends AbstractExtension
{
    public static function name(): string
    {
        return 'Disableable Fixture';
    }

    public static function description(): string
    {
        return 'A plain, disableable extension, for testing Manager::extensions() filtering.';
    }
}
