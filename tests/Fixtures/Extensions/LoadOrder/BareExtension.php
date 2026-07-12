<?php

declare(strict_types=1);

namespace Tests\Fixtures\Extensions\LoadOrder;

use Kopling\Core\Extension\AbstractExtension;

/**
 * No load-order opinion of its own, and doesn't implement `SomeContract` -- a plain bystander
 * for tie-break and "unaffected by someone else's rule" scenarios.
 */
class BareExtension extends AbstractExtension
{
    public static function name(): string
    {
        return 'Bare Fixture';
    }

    public static function description(): string
    {
        return 'No load-order opinion, for testing Resolver\'s alphabetical tie-break.';
    }
}
