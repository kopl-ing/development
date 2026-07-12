<?php

declare(strict_types=1);

namespace Tests\Fixtures\Extensions\LoadOrder;

/**
 * An arbitrary marker interface, standing in for a real one (e.g. a future `HasSettings`) --
 * `Resolver::edges()` dispatches `InfluencesLoadOrder` rules by `instanceof`, so any interface
 * works for testing the mechanism itself.
 */
interface SomeContract
{
}
