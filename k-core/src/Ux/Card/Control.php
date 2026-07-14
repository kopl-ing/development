<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Card;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\View\Component;
use Kopling\Core\Extend\Ux;
use Kopling\Core\Extension\Manager;
use Kopling\Core\Ux\Context;
use Kopling\Core\Ux\SlotResolver;
use Kopling\Core\Ux\UxEntry;

/**
 * The card's own action menu -- resolves/renders `SLOT` the same way `Top`/`Footer` do, wrapped
 * in the generic `Kopling\Core\Ux\Dropdown` for the actual trigger/menu markup. An extension
 * targets `SLOT` with the same `Ux::add()`/`replace()`/`remove()`/`after()`/`before()`/`when()`
 * calls it already knows from `kopling-core::side-navigation` to add a real per-moment action
 * (edit, delete, report, pin, ...). `defaults()` deliberately registers nothing, same reasoning
 * as `Footer::defaults()` -- no fake actions, a real one registers into this slot when it exists.
 */
class Control extends Component
{
    public const SLOT = 'kopling-core::card.control';

    /**
     * @var Collection<int, UxEntry>
     */
    public Collection $entries;

    public function __construct(Manager $manager, public Context $context)
    {
        $this->entries = SlotResolver::resolve(self::SLOT, $manager->ux(), $context);
    }

    public function render(): View
    {
        return view('kopling-core::card.control');
    }

    public static function defaults(Ux $ux): void
    {
        // Intentionally empty.
    }
}
