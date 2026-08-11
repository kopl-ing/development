<?php

declare(strict_types=1);

namespace Tests\Fixtures\Extensions\UserMenuPriority;

use Kopling\Core\Extend\Ux;
use Kopling\Core\Extend\Ux\ProvidesUxEntries;
use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\ChangesUx;
use Kopling\Core\Ux\Community\UserMenu;
use Kopling\Core\Ux\Portal\Navigation\Item;

/**
 * Registers three entries into `UserMenu::SLOT`, deliberately in "wrong" order (default, low,
 * high) -- so `UserMenuTest` can prove `priority()` sorts higher-first regardless of registration
 * order, with the untouched default (0) landing between the two.
 */
class Extension extends AbstractExtension implements ChangesUx
{
    public static function name(): string
    {
        return 'User Menu Priority Fixture';
    }

    public static function description(): string
    {
        return 'Adds three entries to UserMenu::SLOT at different priorities, to test ordering.';
    }

    public function ux(): ProvidesUxEntries
    {
        return Ux::make()
            ->add(Item::class, ['label' => 'Default Priority Item', 'route' => 'kopling-core::community/community'])
            ->in(UserMenu::SLOT)
            ->as('default-priority')
            ->add(Item::class, ['label' => 'Low Priority Item', 'route' => 'kopling-core::community/community'])
            ->in(UserMenu::SLOT)
            ->as('low-priority')
            ->priority(UserMenu::PRIORITY_BOTTOM)
            ->add(Item::class, ['label' => 'High Priority Item', 'route' => 'kopling-core::community/community'])
            ->in(UserMenu::SLOT)
            ->as('high-priority')
            ->priority(UserMenu::PRIORITY_TOP);
    }
}
