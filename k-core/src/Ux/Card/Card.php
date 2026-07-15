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
 * Moment) without owning any markup here itself -- see that event's own docblock.
 */
class Card extends Component
{
    public string $classes;

    public function __construct(public Context $context)
    {
        $event = new RenderingCard($context);
        event($event);

        $this->classes = implode(' ', $event->classes);
    }

    public function render(): View
    {
        return view('kopling-core::card.card');
    }
}
