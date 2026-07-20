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
 * A card's floating badge strip -- straddles the card's own top edge, left-aligned, above
 * `Top`'s title row (see `card.blade.php`: rendered as a sibling of the divide-y section
 * wrapper, still inside the outer `.card` box so `position: absolute` here resolves against
 * the *card's* own edge, not `Top`'s -- `Top` never sees this content at all). A moment's tag
 * badges (`k-extensions/tags`) are the first real registration, but the slot itself doesn't
 * name tags, the same way `Top::SLOT` itself doesn't name avatars -- whatever belongs floating
 * on a card's own edge targets this slot.
 *
 * `$slot` overrides which slot gets resolved, the same convention `Top`/`Body`/`Footer` already
 * use -- `self::SLOT` (Moment cards) when omitted. Kept Moment-scoped by default -- nothing
 * about a Reply calls for this yet -- with a Reply-scoped constant threaded through anyway
 * (`Reply::BADGES_SLOT`) for the identical bleed-prevention reasoning `Top`'s own docblock
 * already gives for `$slot`.
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
