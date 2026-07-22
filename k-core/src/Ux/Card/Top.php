<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Card;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\View\Component;
use Kopling\Core\Extend\Ux;
use Kopling\Core\Extension\Manager;
use Kopling\Core\Ux\Context;
use Kopling\Core\Ux\Person\Avatar;
use Kopling\Core\Ux\SlotResolver;
use Kopling\Core\Ux\UxEntry;

/**
 * A card's header row -- Title, avatar, author, timestamp, `Control`. `$slot` overrides which
 * slot gets resolved (`self::SLOT` for Moment cards when omitted), so Discussions' Reply cards
 * can reuse this same extensible shape under their own slot name instead of duplicating it.
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
