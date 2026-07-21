<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Compose;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\View\Component;
use Kopling\Core\Extension\Manager;
use Kopling\Core\Ux\SlotResolver;
use Kopling\Core\Ux\UxEntry;

/**
 * A card region offering pluggable content-creation modes -- generic infrastructure, same
 * category as `Card\Top`/`Body`/`Footer`: core owns the slot/resolver shape, any extension
 * registers into it. `composer` registers its own default `text` entry here; `poll` (or any
 * future mode) registers alongside it via this real class constant, not a bare string pointing
 * at another extension.
 */
class Modes extends Component
{
    public const SLOT = 'kopling-core::compose.modes';

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
        return view('kopling-core::ux.compose.modes');
    }
}
