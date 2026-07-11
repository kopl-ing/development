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
 * The main content area of a card. Resolves/renders `SLOT` the same way `Top`/`Footer` do --
 * default content (the title and body text) is just `Content`, registered like anything else
 * would be, not hardcoded into this class.
 */
class Body extends Component
{
    public const SLOT = 'kopling-core::card.body';

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
        return view('kopling-core::card.body');
    }

    public static function defaults(Ux $ux): void
    {
        $ux->add(Content::class)->in(self::SLOT)->as('content');
    }
}
