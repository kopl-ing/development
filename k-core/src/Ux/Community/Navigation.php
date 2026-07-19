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
 *
 * `$data['slot']` overrides which slot gets resolved -- `self::SLOT` (Community's own nav
 * links) when omitted. This is what lets Admin/Style Guide reuse this exact component (self-
 * wrapping `<ul class="menu">`, itself one entry inside `Community\Chrome`'s generic sidebar
 * slot) for their own, differently-named navigation slots instead of each hand-rolling the same
 * `<ul>`+`<li>` shape -- same "$slot override, defaults preserve current behavior" trick
 * `Card\Top`/`Body`/`Footer` already use. Passed via `$data` (not a direct constructor param,
 * unlike `Top`'s own `?string $slot`) because, unlike those, this is itself registered *as* a
 * `Ux::add()` entry -- reached through `<x-dynamic-component :data="...">`, which only ever
 * supplies `$data`/`$context`, never arbitrary extra constructor arguments.
 */
class Navigation extends Component
{
    public const SLOT = 'kopling-core::community.navigation';

    /**
     * @var Collection<int, UxEntry>
     */
    public Collection $entries;

    public function __construct(Manager $manager, public string $surface = 'menu', public array $data = [])
    {
        $this->entries = SlotResolver::resolve($data['slot'] ?? self::SLOT, $manager->ux());
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
            'icon' => 'kopling-core::home',
        ])
            ->in(self::SLOT)
            ->as('home');
    }
}
