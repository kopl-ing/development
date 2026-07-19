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
 * The signed-in person's own avatar in the community topbar -- registers itself there the same
 * way `ThemeSwitcher` does, but is itself a second, nested slot (`self::SLOT`) an extension adds
 * its own menu items to, the same two-level pattern `Card\Top`/`Card\Control` already establish
 * (an outer slot entry that's itself a dropdown resolving its own inner slot).
 *
 * A bare `Context` (no `$subject`) already carries the signed-in actor -- its constructor
 * defaults `$actor ??= Auth::user()` -- so this reads that instead of asking `Auth::user()`
 * again itself. The view builds its own `new Context(subject: $context->getActor())` right where
 * it renders `Card\Avatar` (there's no Moment here for `$context` itself to be about), rather
 * than `Avatar` needing to know anything about actors at all -- see its own docblock.
 *
 * Renders nothing at all for a guest -- there's no signed-in person's avatar to show. Renders a
 * bare avatar, no dropdown wrapper, when nothing is registered here (an install with no
 * extension adding anything to this menu yet) -- same "don't show an interactive dropdown with
 * nothing in it" rule `Card\Control` already follows for its own slot.
 */
class UserMenu extends Component
{
    public const SLOT = 'kopling-core::community.user-menu';

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
     * Registers itself into the topbar (like `ThemeSwitcher` does), and its own one default
     * inside its own nested slot -- a link back to the Community portal, the same route/icon
     * `Community\Navigation`'s own "Home" entry uses, so any page rendering this menu (Style
     * Guide's own topbar, Admin's once it grows one) always has a way back regardless of which
     * portal it's actually on. Same "a component's own default lives on itself" rule
     * `Navigation::defaults()` already follows for its own "Home" entry.
     *
     * `hideOnPortal` suppresses this exact entry while already on the Community portal itself --
     * `Item`'s own render checks it against `$context->isPortal()`, which this menu's `$context`
     * carries automatically (see `Context`'s own constructor).
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
            ->as('community-link');
    }
}
