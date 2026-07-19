<?php

declare(strict_types=1);

namespace Tests\Fixtures\Extensions\UserMenuEntry;

use Kopling\Core\Extend\Ux;
use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\ChangesUx;
use Kopling\Core\Ux\Community\UserMenu;
use Kopling\Core\Ux\Portal\Navigation\Item;

/**
 * Registers one real, renderable entry (`Portal\Navigation\Item`) into `UserMenu::SLOT`, so
 * `UserMenuTest` can assert the dropdown actually renders once something targets it, without
 * needing a whole extension's own view just to prove the mechanism works -- same reasoning as
 * `CardControlEntry`.
 */
class Extension extends AbstractExtension implements ChangesUx
{
    public static function name(): string
    {
        return 'User Menu Entry Fixture';
    }

    public static function description(): string
    {
        return 'Adds one entry to UserMenu::SLOT for testing the user menu dropdown.';
    }

    public function ux(): Ux
    {
        return Ux::make()
            ->add(Item::class, ['label' => 'Fixture Item', 'route' => 'kopling-core::community/community'])
            ->in(UserMenu::SLOT)
            ->as('entry');
    }
}
