<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Card;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Kopling\Core\Ux\Card\Event\RenderingCard;
use Kopling\Core\Ux\Context;

/**
 * The outer shell of a content card. `Top`/`Body`/`Footer` each render their own padded section,
 * wrapped together in one `divide-y overflow-hidden` div; `Badges` renders *outside* that wrapper
 * so its absolute-positioned badge row (straddling the card's own top edge) doesn't get clipped
 * by the wrapper's `overflow-hidden`.
 *
 * `$topSlot`/`$badgesSlot`/`$bodySlot`/`$footerSlot` let a caller point at different slot names
 * entirely (Discussions' Reply cards), so Moment-only registrations don't bleed onto a Reply.
 *
 * `$url` is `$context->getSubjectUrl()`, resolved once for `card.blade.php`'s stretched-link
 * overlay/aura-glow/caret -- all gated on whether the subject actually has somewhere to go.
 */
class Card extends Component
{
    public string $classes;

    public ?string $url = null;

    public function __construct(
        public Context $context,
        public ?string $topSlot = null,
        public ?string $badgesSlot = null,
        public ?string $bodySlot = null,
        public ?string $footerSlot = null,
    ) {
        $event = new RenderingCard($context);
        event($event);

        $this->url = $context->getSubjectUrl();
        $this->classes = implode(' ', $event->classes);
    }

    public function render(): View
    {
        return view('kopling-core::card.card');
    }
}
