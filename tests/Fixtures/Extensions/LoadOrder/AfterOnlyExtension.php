<?php

declare(strict_types=1);

namespace Tests\Fixtures\Extensions\LoadOrder;

use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\LoadOrder\LoadsAfter;

/**
 * Implements only `LoadsAfter`, deliberately never `LoadsBefore` -- proves `Resolver` dispatches
 * on `instanceof` per direction rather than requiring both, the entire point of splitting what
 * used to be one `HasLoadOrder` interface in two.
 */
class AfterOnlyExtension extends AbstractExtension implements LoadsAfter
{
    /**
     * @param  array<string>  $after  Composer package names this must load after.
     */
    public function __construct(protected array $after = [])
    {
    }

    public static function name(): string
    {
        return 'After-Only Fixture';
    }

    public static function description(): string
    {
        return 'Implements LoadsAfter only, for testing Resolver treats each direction independently.';
    }

    /**
     * @return array<string>
     */
    public function loadAfter(): array
    {
        return $this->after;
    }
}
