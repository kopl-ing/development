<?php

declare(strict_types=1);

namespace Tests\Fixtures\Extensions\UxRemover;

use Kopling\Core\Extend\Ux;
use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\ChangesUx;

/**
 * Removes UxAdder's "gadget" entry by its fully-qualified id, for testing ChangesUx remove().
 */
class Extension extends AbstractExtension implements ChangesUx
{
    public static function name(): string
    {
        return 'Ux Remover Fixture';
    }

    public static function description(): string
    {
        return 'Removes UxAdder\'s "gadget" entry, for testing ChangesUx remove().';
    }

    public function ux(): Ux
    {
        return Ux::make()->remove('tests-fixtures-ux-adder::gadget');
    }
}
