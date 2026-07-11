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
 * A card's header row -- avatar, author, timestamp, flowing left to right; a `Control` (or
 * anything else) placed last floats to the far right via its own `ml-auto`, not something
 * `Top` imposes on its children. Resolves and renders `SLOT` exactly like the page-level
 * `Slot` component does, just bound to this card's own `Context` -- an extension targets
 * `SLOT` with the same `Ux::add()`/`replace()`/`remove()`/`after()`/`before()`/`when()` calls
 * it already knows from `core::side-navigation`.
 */
class Top extends Component
{
    public const SLOT = 'core::card.header';

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
        return view('core::card.top');
    }

    /**
     * No `Tag` here on purpose -- tagging is a future extension's own concern, registered
     * into this same slot when it exists, not a default core hands out.
     */
    public static function defaults(Ux $ux): void
    {
        $ux->add(Avatar::class)->in(self::SLOT)->as('avatar')
            ->add(Author::class)->in(self::SLOT)->as('author')->after('avatar')
            ->add(Timestamp::class)->in(self::SLOT)->as('timestamp')->after('author')
            ->add(Control::class)->in(self::SLOT)->as('control')->after('timestamp');
    }
}
