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
 * The main content area of a card. Resolves `SLOT` the same way `Top`/`Footer` do -- default
 * content (the title and body text) is just `Content`, registered like anything else would be,
 * not hardcoded into this class. Unlike `Top`/`Footer`, which render every entry inline within
 * one shared row, each entry resolved here stacks in its own boxed section -- see the "why" in
 * `card.body`'s own view, and `UxEntry::$flush` for the one way an entry opts out of the boxing.
 *
 * `$slot` overrides which slot gets resolved -- see `Top`'s own docblock for why (Discussions'
 * Reply cards target their own slot here instead of Moments').
 */
class Body extends Component
{
    public const SLOT = 'kopling-core::card.body';

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
        return view('kopling-core::card.body');
    }

    public static function defaults(Ux $ux): void
    {
        $ux->add(Content::class)->in(self::SLOT)->as('content');
    }
}
