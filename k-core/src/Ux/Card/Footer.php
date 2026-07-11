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
 * The bottom row of a card. Reuses daisyUI's own `card-actions` part class, which is just a
 * flex row itself; `Row`/`Column` arrange whatever's placed inside further, if a single flex
 * row isn't enough. Resolves/renders `SLOT` the same way `Top` does.
 *
 * `defaults()` deliberately registers nothing -- no fake reply/reaction counts. There's no
 * real reactions/reply feature behind this yet (`k-extensions/reactions` is still a bare
 * stub); a real one registers into this same slot when it exists, rather than this class
 * shipping a placeholder count.
 */
class Footer extends Component
{
    public const SLOT = 'core::card.footer';

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
        return view('core::card.footer');
    }

    public static function defaults(Ux $ux): void
    {
        // Intentionally empty.
    }
}
