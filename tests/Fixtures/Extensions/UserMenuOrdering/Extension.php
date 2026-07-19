<?php

declare(strict_types=1);

namespace Tests\Fixtures\Extensions\UserMenuOrdering;

use Kopling\Core\Extend\Ux;
use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\ChangesUx;
use Kopling\Core\Ux\Community\UserMenu;
use Kopling\Core\Ux\Portal\Navigation\Item;

/**
 * Registers two entries into `UserMenu::SLOT`, deliberately in "wrong" order -- the plain one
 * first, the `->first()`-pinned one second -- so `UserMenuTest` can prove `first()` overrides
 * registration order rather than merely happening to match it.
 */
class Extension extends AbstractExtension implements ChangesUx
{
    public static function name(): string
    {
        return 'User Menu Ordering Fixture';
    }

    public static function description(): string
    {
        return 'Adds two entries to UserMenu::SLOT, one pinned first(), to test ordering.';
    }

    public function ux(): Ux
    {
        return Ux::make()
            ->add(Item::class, ['label' => 'Second Item', 'route' => 'kopling-core::community/community'])
            ->in(UserMenu::SLOT)
            ->as('second')
            ->add(Item::class, ['label' => 'Pinned Item', 'route' => 'kopling-core::community/community'])
            ->in(UserMenu::SLOT)
            ->as('pinned')
            ->first();
    }
}
