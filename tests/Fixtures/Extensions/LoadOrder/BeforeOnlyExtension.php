<?php

declare(strict_types=1);

namespace Tests\Fixtures\Extensions\LoadOrder;

use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\LoadOrder\LoadsBefore;

/**
 * Implements only `LoadsBefore`, deliberately never `LoadsAfter` -- the inverse of
 * `AfterOnlyExtension`, same reasoning.
 */
class BeforeOnlyExtension extends AbstractExtension implements LoadsBefore
{
    /**
     * @param  array<string>  $before  Composer package names this must load before.
     */
    public function __construct(protected array $before = [])
    {
    }

    public static function name(): string
    {
        return 'Before-Only Fixture';
    }

    public static function description(): string
    {
        return 'Implements LoadsBefore only, for testing Resolver treats each direction independently.';
    }

    /**
     * @return array<string>
     */
    public function loadBefore(): array
    {
        return $this->before;
    }
}
