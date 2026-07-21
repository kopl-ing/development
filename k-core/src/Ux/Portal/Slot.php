<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Portal;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\View\Component;
use Kopling\Core\Extension\Manager;
use Kopling\Core\Ux\Context;
use Kopling\Core\Ux\SlotResolver;
use Kopling\Core\Ux\UxEntry;

/**
 * Renders whatever's registered into a named slot, filtered to what the current person can see.
 * Generic on purpose -- a Portal's own layout decides which slot names exist and where each is
 * placed. `$context`, when given, binds every resolved entry to it (a `Tag` being edited, say);
 * omit it for a page-level slot with nothing to bind.
 */
class Slot extends Component
{
    /**
     * @var Collection<int, UxEntry>
     */
    public Collection $entries;

    public function __construct(Manager $manager, public string $name, public ?Context $context = null)
    {
        $this->entries = SlotResolver::resolve($this->name, $manager->ux(), $this->context);
    }

    public function render(): View
    {
        return view('kopling-core::portal.slot');
    }
}
