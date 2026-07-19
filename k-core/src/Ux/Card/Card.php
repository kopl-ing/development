<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Card;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Kopling\Core\Ux\Card\Event\RenderingCard;
use Kopling\Core\Ux\Context;

/**
 * The outer shell of a content card -- a discussion preview today, whatever else wants this
 * shape later. Owns the daisyUI `card`/`card-body` structure; `Top`/`Body`/`Footer` are
 * plain content inside that one `card-body` (daisyUI itself has no separate "card header"
 * part), each free to be used, reordered, or left out. The wrapper's own markup isn't
 * slot-driven the way `Top`/`Body`/`Footer` are, but it isn't fully sealed either: dispatching
 * `RenderingCard` lets an extension append a class to it (e.g. a colored border for a pinned
 * Moment) without owning any markup here itself -- see that event's own docblock. A caller can
 * append its own classes the same way any Blade component tag would (`<x-k::card.card
 * class="bg-base-200" ... />`), merged via `$attributes` in the view rather than a dedicated prop.
 *
 * `$topSlot`/`$bodySlot`/`$footerSlot` forward straight to `Top`/`Body`/`Footer`'s own `$slot`
 * override -- `null` (Moment cards, unchanged) unless a caller wants this exact extensible shape
 * for something else entirely (Discussions' own Reply cards: `new Context(subject: $reply)` plus
 * its own three slot names, rather than Reply and Moment sharing one global slot family where
 * every Moment-only registration -- reactions, discussions' own teaser/engage -- would otherwise
 * bleed onto a Reply that has none of those concepts).
 */
class Card extends Component
{
    public string $classes;

    public function __construct(
        public Context $context,
        public ?string $topSlot = null,
        public ?string $bodySlot = null,
        public ?string $footerSlot = null,
    ) {
        $event = new RenderingCard($context);
        event($event);

        $this->classes = implode(' ', $event->classes);
    }

    public function render(): View
    {
        return view('kopling-core::card.card');
    }
}
