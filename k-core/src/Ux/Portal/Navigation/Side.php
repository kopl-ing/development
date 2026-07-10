<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Portal\Navigation;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\View\Component;
use Kopling\Core\Extension\Manager;
use Kopling\Core\Portal\Portal;
use Kopling\Core\Ux\SlotResolver;
use Kopling\Core\Ux\UxEntry;

/**
 * Renders whatever's registered into the "core::side-navigation" slot -- by Core itself or
 * any extension implementing ChangesUx -- filtered to what the current person can see.
 */
class Side extends Component
{
    /**
     * @var Collection<int, UxEntry>
     */
    public Collection $entries;

    public function __construct(Manager $manager, public Portal $portal)
    {
        $this->entries = SlotResolver::resolve('core::side-navigation', $manager->ux());
    }

    public function render(): View
    {
        return view('core::portal.navigation.side');
    }
}
