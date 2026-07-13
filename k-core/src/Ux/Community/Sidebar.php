<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Community;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\View\Component;
use Kopling\Core\Extension\Manager;
use Kopling\Core\Ux\SlotResolver;
use Kopling\Core\Ux\UxEntry;

/**
 * The Community portal's left-column supplementary content -- arbitrary widget-shaped blocks
 * (a "pulse" of live counts, popular tags, ...), not navigation. Owns
 * `kopling-core::community.sidebar`, the slot `layouts/community.blade.php` already declares;
 * resolves/renders it exactly like `Card\Top` does for its own slot.
 *
 * Navigation links (Home feed, Popular, ...) live in `Navigation`'s own
 * `kopling-core::community.navigation` slot instead, deliberately kept separate: this slot's
 * entries render as free-form blocks (see `kopling-widgets::pulse`/`tags`, each a `<div
 * class="card">`), which would be invalid HTML nested inside `Navigation`'s `<ul class="menu">`
 * -- and a slot that mixed both couldn't be resolved on its own for the mobile dock, which only
 * ever wants nav-shaped entries.
 *
 * No `defaults()` -- nothing in Core registers into this slot itself, only extensions
 * (`kopling-widgets`) do; kept purely as the render/resolve half of the slot contract.
 */
class Sidebar extends Component
{
    public const SLOT = 'kopling-core::community.sidebar';

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
        return view('kopling-core::community.sidebar');
    }
}
