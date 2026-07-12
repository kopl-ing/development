<?php

declare(strict_types=1);

namespace Tests\Fixtures\Extensions\UxReplacer;

use Kopling\Core\Extend\Ux;
use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\ChangesUx;

/**
 * Targets UxAdder's "widget" entry by its fully-qualified id -- package name deliberately sorts
 * after "tests-fixtures/ux-adder" alphabetically, so `LoadOrder\Resolver`'s tie-break processes
 * Adder first and this entry actually exists by the time this replace() runs.
 */
class Extension extends AbstractExtension implements ChangesUx
{
    public static function name(): string
    {
        return 'Ux Replacer Fixture';
    }

    public static function description(): string
    {
        return 'Replaces UxAdder\'s "widget" entry, for testing ChangesUx replace().';
    }

    public function ux(): Ux
    {
        return Ux::make()
            ->replace('tests-fixtures-ux-adder::widget', 'fixture::widget-v2', ['replaced' => true]);
    }
}
