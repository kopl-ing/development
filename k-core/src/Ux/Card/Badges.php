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
 * A card's floating badge strip -- straddles the card's own top edge, above `Top`'s title row.
 * A moment's tag badges are the first real registration, but the slot itself doesn't name tags.
 * `$slot` overrides which slot gets resolved, same convention as `Top`/`Body`/`Footer`.
 */
class Badges extends Component
{
    public const SLOT = 'kopling-core::card.badges';

    /**
     * @var Collection<int, UxEntry>
     */
    public Collection $entries;

    public function __construct(Manager $manager, public Context $context, ?string $slot = null)
    {
        $this->entries = SlotResolver::resolve($slot ?? self::SLOT, $manager->ux(), $context);
    }

    public function render(): View
    {
        return view('kopling-core::card.badges');
    }

    public static function defaults(Ux $ux): void
    {
        // Intentionally empty -- same reasoning as Footer::defaults(): no fake badges, a real
        // one (k-extensions/tags) registers into this slot when it exists.
    }
}
