<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Community;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\View\Component;
use Kopling\Core\Extend\Ux;
use Kopling\Core\Extension\Manager;
use Kopling\Core\Ux\Context;
use Kopling\Core\Ux\Portal\Navigation\Item;
use Kopling\Core\Ux\SlotResolver;
use Kopling\Core\Ux\UxEntry;

/**
 * The signed-in person's own avatar in the community topbar -- itself a second, nested slot
 * (`self::SLOT`) an extension adds its own menu items to. Renders nothing for a guest, and a bare
 * avatar (no dropdown) when nothing is registered here.
 */
class UserMenu extends Component
{
    public const SLOT = 'kopling-core::community.user-menu';

    /**
     * For an entry that should lead the dropdown regardless of what else registers here (a
     * Portal's own link -- Admin, Moderation, Style Guide) -- see `UxEntry::$priority`.
     */
    public const PRIORITY_TOP = 100;

    /**
     * For an entry that should trail the dropdown regardless of what else registers here (Core's
     * own "Log out") -- see `UxEntry::$priority`.
     */
    public const PRIORITY_BOTTOM = -100;

    public Context $context;

    /**
     * @var Collection<int, UxEntry>
     */
    public Collection $entries;

    public function __construct(Manager $manager)
    {
        $this->context = new Context();
        $this->entries = SlotResolver::resolve(self::SLOT, $manager->ux(), $this->context);
    }

    public function render(): View
    {
        return view('kopling-core::community.user-menu');
    }

    /**
     * `hideOnPortal` suppresses the community-link entry while already on that portal --
     * checked against `$context->isPortal()`. "Log out" trails every other entry via
     * `PRIORITY_BOTTOM`; a Portal's own link uses `PRIORITY_TOP` (see Admin/Moderation/Style
     * Guide's own registrations) to lead regardless of extension load order.
     */
    public static function defaults(Ux $ux): void
    {
        $ux->add(self::class)
            ->in('kopling-core::community.topbar')
            ->as('user-menu')
            ->add(Item::class, [
                'label' => __('kopling-core::community.community'),
                'route' => 'kopling-core::community/community',
                'icon' => 'kopling-core::home',
                'hideOnPortal' => 'kopling-core::community',
            ])
            ->in(self::SLOT)
            ->as('community-link')
            ->add(LogoutItem::class)
            ->in(self::SLOT)
            ->as('logout')
            ->priority(self::PRIORITY_BOTTOM);
    }
}
