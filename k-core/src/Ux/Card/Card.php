<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Card;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Kopling\Core\Ux\Card\Event\RenderingCard;
use Kopling\Core\Ux\Context;

/**
 * The outer shell of a content card -- a discussion preview today, whatever else wants this
 * shape later. Owns the daisyUI `card` structure, but not `card-body`: instead of one padded
 * region holding everything, `Top`/`Body`/`Footer` are each rendered as their own full-bleed,
 * independently padded section, separated by `divide-y` rather than internal margins (daisyUI
 * itself has no separate "card header"/"card footer" part), wrapped together in one inner
 * `divide-y overflow-hidden rounded-[inherit]` `<div>` -- `Badges` renders *outside* that inner
 * wrapper, a direct sibling of it, so its `position: absolute` (straddling the outer `.card`
 * box's own top edge) never gets clipped by the inner wrapper's own `overflow-hidden`, which
 * exists only to keep `Top`/`Body`/`Footer`'s own corners matching the outer rounded shape.
 * Each of `Top`/`Body`/`Footer`/`Badges` owns the `@if ($entries->isNotEmpty())` guard around
 * its own root `<div>` (see `top.blade.php` etc.) so a slot with nothing registered in it
 * contributes no DOM node at all -- `divide-y` only ever draws a line between sections that
 * actually rendered, never an empty padded strip. Each is still free to be used, reordered, or
 * left out. The wrapper's own markup isn't slot-driven the way `Top`/`Body`/`Footer` are, but
 * it isn't fully sealed either: dispatching `RenderingCard` lets an extension append a class to
 * it (e.g. a colored border for a pinned Moment) without owning any markup here itself -- see
 * that event's own docblock. A caller can append its own classes the same way any Blade
 * component tag would (`<x-k::card.card class="bg-base-200" ... />`), merged via `$attributes`
 * in the view rather than a dedicated prop.
 *
 * `$topSlot`/`$badgesSlot`/`$bodySlot`/`$footerSlot` forward straight to `Top`/`Badges`/`Body`/
 * `Footer`'s own `$slot` overrides -- `null` (Moment cards, unchanged) unless a caller wants this
 * exact extensible shape for something else entirely (Discussions' own Reply cards: `new
 * Context(subject: $reply)` plus its own slot names, rather than Reply and Moment sharing one
 * global slot family where every Moment-only registration -- reactions, discussions' own
 * teaser/engage, tags' own badge row -- would otherwise bleed onto a Reply that has none of
 * those concepts).
 *
 * `$url` is `$context->getSubjectUrl()` -- the same `Extend\Model::linksTo()` lookup `Title`
 * and Discussions' own `engage` link already call independently -- resolved once here so the
 * view can render the whole-card stretched-link overlay, the aura-glow wrapper, and the trailing
 * caret icon (all in `card.blade.php`) only when the subject actually has somewhere to go. A
 * subject with no `linksTo()` registration (a Reply card, or a Moment with Discussions
 * uninstalled) gets none of this, automatically -- there's no separate "is this card clickable"
 * flag to keep in sync with the real link.
 *
 * `$classes` is only ever `RenderingCard`'s own accumulated contributions now -- see that
 * event's docblock for why `card.blade.php` renders them on a dedicated decoration layer
 * instead of merging them into `.card`'s own class list. `group`/`cursor-pointer` (needed on
 * `.card` itself so `Title`'s `group-hover:text-primary` has something to react to) are added
 * directly in the view instead, conditioned on `$url` the same way everything else here is.
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
