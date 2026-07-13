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
 * The Community portal's primary nav links -- Home feed, Popular, and whatever an extension
 * (Following, Bookmarks, ...) registers alongside them. Split out of `Sidebar` (which now only
 * holds arbitrary supplementary content -- widgets -- not navigation) so a slot exists that's
 * guaranteed to contain nothing but nav-shaped entries: the mobile dock rendering below needs
 * that guarantee, since it can't sensibly render a "popular tags" widget as a bottom-nav button.
 *
 * Rendered twice per page by `community/chrome.blade.php` -- once as `$surface = 'menu'` (the
 * desktop sidebar list) and once as `$surface = 'dock'` (the mobile bottom nav, hidden above
 * `md`) -- each a separate `SlotResolver::resolve()` call, same cost pattern already used by
 * `Slot` for topbar/rail/composer. Every entry always renders into both surfaces -- there's no
 * per-entry opt-out -- `$surface` just decides which markup shape this render pass uses.
 * Passed straight through to each entry's own component as an extra Blade attribute (`Item`
 * picks it up; anything else that doesn't declare a `$surface` prop just ignores it via Blade's
 * attribute bag) -- so which markup an entry renders as is a decision made here, at the render
 * call site, never by whatever registered it.
 *
 * `defaults()` only registers Home feed -- Popular has no real sort/ranking behind it yet
 * (see `IndexController`, which just does `Moment::latest()`), so it isn't registered as a
 * placeholder link to nowhere, same reasoning as `Card\Footer::defaults()` staying empty
 * until reactions are real.
 */
class Navigation extends Component
{
    public const SLOT = 'kopling-core::community.navigation';

    /**
     * @var Collection<int, UxEntry>
     */
    public Collection $entries;

    public function __construct(Manager $manager, public string $surface = 'menu')
    {
        $this->entries = SlotResolver::resolve(self::SLOT, $manager->ux());
    }

    public function render(): View
    {
        return view('kopling-core::community.navigation');
    }

    public static function defaults(Ux $ux): void
    {
        $ux->add(Item::class, [
            'label' => __('kopling-core::community.home'),
            'route' => 'kopling-core::community/community',
            'icon' => self::HOME_ICON,
        ])
            ->in(self::SLOT)
            ->as('home');
    }

    /**
     * Same inline-svg style as every other icon in this codebase (see `Item`'s own docblock) --
     * a plain house glyph, 24x24 viewBox, stroke-only.
     */
    private const HOME_ICON = <<<'SVG'
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M3 10.5 12 3l9 7.5"/>
            <path d="M5 9.5V20a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1V9.5"/>
        </svg>
        SVG;
}
