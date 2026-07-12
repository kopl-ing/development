<?php

declare(strict_types=1);

namespace Kopling\Composer;

use Kopling\Core\Extend\Ux;
use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\ChangesUx;
use Kopling\Core\People\Person;

/**
 * A "share a moment" composer at the top of the feed -- the compose-first entry the charter
 * calls for (a person + their short moment, title optional). Registers into the feed's own
 * `content-top` slot (not the portal chrome), so it shows above the feed and nowhere else,
 * and only for a signed-in person (`when()`); a guest sees the topbar's sign-in instead.
 * Posting prepends the new moment through core's own card, live, via htmx.
 */
class Extension extends AbstractExtension implements ChangesUx
{
    public static function name(): string
    {
        return 'Composer';
    }

    public static function description(): string
    {
        return 'Share a moment from the top of the feed.';
    }

    public function ux(): Ux
    {
        return Ux::make()
            ->add('kopling-composer::composer')
            ->in('kopling-core::community.content-top')
            ->as('composer')
            ->when(fn (?Person $person) => $person !== null);
    }
}
