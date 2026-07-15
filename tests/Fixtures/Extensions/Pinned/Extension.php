<?php

declare(strict_types=1);

namespace Tests\Fixtures\Extensions\Pinned;

use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\CannotBeDisabled;

/**
 * Implements `CannotBeDisabled` -- for testing that `Manager::extensions()` never filters it
 * out, regardless of stored `EnabledExtensions` state.
 */
class Extension extends AbstractExtension implements CannotBeDisabled
{
    public static function name(): string
    {
        return 'Pinned Fixture';
    }

    public static function description(): string
    {
        return 'A CannotBeDisabled extension, for testing Manager::extensions() filtering.';
    }
}
