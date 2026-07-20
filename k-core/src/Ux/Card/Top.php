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
 * A card's header row. For a Moment card, `Title` leads (see `Title`'s own docblock for how
 * its `flex-1` pushes everything after it to the row's right edge) followed by avatar, author,
 * timestamp, flowing left to right; a `Control` (or anything else) placed last floats further
 * right still via its own `ml-auto`, not something `Top` imposes on its children -- redundant
 * once `Title` is already claiming the row's free space, but a harmless no-op, and still the
 * correct behavior if `Title` is ever removed. Resolves and renders `SLOT` exactly like the
 * page-level `Slot` component does, just bound to this card's own `Context` -- an extension
 * targets `SLOT` with the same `Ux::add()`/`replace()`/`remove()`/`after()`/`before()`/`when()`
 * calls it already knows from `kopling-core::community.navigation`.
 *
 * `$slot` overrides which slot actually gets resolved -- `self::SLOT` (Moment cards) when
 * omitted, so this stays fully backward compatible. A second content type wanting this exact
 * extensible top-row shape (Discussions' own Reply cards) passes its own slot instead of `Top`
 * needing to be duplicated just to target a different, non-Moment-scoped name -- see `Card`,
 * which is what actually threads this through from `<x-k::card.card>`. `Title` is only ever
 * registered into `self::SLOT` (below), never into a Reply's own slot -- a reply has no title
 * of its own, so its own top row (avatar, author, timestamp; see `discussions/src/Extension.php`)
 * keeps the plain left-aligned order this whole card shape used to have everywhere.
 *
 * A moment's tag badges float on the *card's* own top edge instead of living in this row -- see
 * `Card\Badges`, a sibling `Card` renders outside `Top` entirely (not something `Top` itself
 * resolves, unlike an earlier version of this docblock that briefly had it as a second row here).
 */
class Top extends Component
{
    public const SLOT = 'kopling-core::card.header';

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
        return view('kopling-core::card.top');
    }

    public static function defaults(Ux $ux): void
    {
        $ux
            ->add(Title::class)->in(self::SLOT)->as('title')
            ->add(Avatar::class)->in(self::SLOT)->as('avatar')->after('title')
            ->add(Author::class)->in(self::SLOT)->as('author')->after('avatar')
            ->add(Timestamp::class)->in(self::SLOT)->as('timestamp')->after('author')
            ->add(Control::class)->in(self::SLOT)->as('control')->after('timestamp');
    }
}
