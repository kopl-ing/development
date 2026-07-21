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
 * (a "pulse" of live counts, popular tags), not navigation. Kept separate from `Navigation`'s
 * own slot since these render as free-form `<div class="card">` blocks, invalid nested inside
 * `Navigation`'s `<ul>`. No `defaults()` -- only extensions register here, Core doesn't.
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
