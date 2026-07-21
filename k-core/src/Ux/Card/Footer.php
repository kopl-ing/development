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
 * The bottom row of a card, on daisyUI's `card-actions`. `defaults()` registers nothing --
 * extensions (reactions, discussions) populate it. `$slot` overrides which slot gets resolved,
 * same convention as `Top`.
 */
class Footer extends Component
{
    public const SLOT = 'kopling-core::card.footer';

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
        return view('kopling-core::card.footer');
    }

    public static function defaults(Ux $ux): void
    {
        // Intentionally empty.
    }
}
