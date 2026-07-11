<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Community;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\View\Component;
use Kopling\Core\Extend\Ux;
use Kopling\Core\Extension\Manager;
use Kopling\Core\Ux\Portal\Navigation\Item;
use Kopling\Core\Ux\SlotResolver;
use Kopling\Core\Ux\UxEntry;

/**
 * The Community portal's primary nav block -- Home feed, Popular, and whatever an extension
 * (Following, Bookmarks, ...) registers alongside them. Page-level, not bound to a Context,
 * same reasoning as `Portal\Slot`/`core::side-navigation` -- there's no single Moment this
 * list renders for. Owns `core::community.sidebar`, the slot `layouts/community.blade.php`
 * already declares; resolves/renders it exactly like `Card\Top` does for its own slot.
 *
 * `defaults()` only registers Home feed -- Popular has no real sort/ranking behind it yet
 * (see `IndexController`, which just does `Moment::latest()`), so it isn't registered as a
 * placeholder link to nowhere, same reasoning as `Card\Footer::defaults()` staying empty
 * until reactions are real.
 */
class Sidebar extends Component
{
    public const SLOT = 'core::community.sidebar';

    /**
     * @var Collection<int, UxEntry>
     */
    public Collection $entries;

    public function __construct(Manager $manager)
    {
        $this->entries = SlotResolver::resolve(self::SLOT, $manager->ux());
    }

    public function render(): View
    {
        return view('core::community.sidebar');
    }

    public static function defaults(Ux $ux): void
    {
        $ux->add(Item::class, ['label' => 'Home feed', 'route' => 'core::community/community'])
            ->in(self::SLOT)
            ->as('home');
    }
}
