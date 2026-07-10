<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Portal;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\View\Component;
use Kopling\Core\Extension\Manager;
use Kopling\Core\Ux\SlotResolver;
use Kopling\Core\Ux\UxEntry;

/**
 * Renders whatever's registered into a named slot -- by Core itself or any extension
 * implementing ChangesUx -- filtered to what the current person can see. Generic on purpose:
 * a Portal's own layout decides which slot names exist and where each is placed (a wrapping
 * <aside>, a <nav>, a bare <div>, whatever fits that layout's own shape) -- this component
 * only resolves and renders, it has no opinion about the markup around it. Different Portal
 * layouts can (and are expected to) define entirely different slot maps; nothing here is
 * specific to any one of them.
 */
class Slot extends Component
{
    /**
     * @var Collection<int, UxEntry>
     */
    public Collection $entries;

    public function __construct(Manager $manager, public string $name)
    {
        $this->entries = SlotResolver::resolve($this->name, $manager->ux());
    }

    public function render(): View
    {
        return view('core::portal.slot');
    }
}
