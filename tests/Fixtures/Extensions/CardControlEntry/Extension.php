<?php

declare(strict_types=1);

namespace Tests\Fixtures\Extensions\CardControlEntry;

use Kopling\Core\Extend\Ux;
use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\ChangesUx;
use Kopling\Core\Ux\Card\Control;
use Kopling\Core\Ux\Card\Row;

/**
 * Registers one real, renderable entry (`Card\Row` -- already core, needs no props) into
 * `Control::SLOT`, so `CardControlTest` can assert the dropdown actually renders once something
 * targets it, without needing a whole extension's own view just to prove the mechanism works.
 */
class Extension extends AbstractExtension implements ChangesUx
{
    public static function name(): string
    {
        return 'Card Control Entry Fixture';
    }

    public static function description(): string
    {
        return 'Adds one entry to Control::SLOT for testing the card control dropdown.';
    }

    public function ux(): Ux
    {
        return Ux::make()
            ->add(Row::class)
            ->in(Control::SLOT)
            ->as('entry');
    }
}
