<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Card\Event;

use Kopling\Core\Ux\Context;

/**
 * Dispatched once per `Card` render (see `Card`'s own constructor) so an extension can append a
 * class for the card's own decoration -- e.g. a colored border/wash for a pinned Moment.
 * `$classes` accumulates rather than replaces, so more than one listener can each contribute
 * independently without needing to know about the others. Same mutable-event shape as
 * `Content\Event\QueryingMoments`/`Authentication\Event\AttemptLogin`, wired through the same
 * `ListensToEvents`/`Manager::listeners()` mechanism.
 *
 * These classes render on a dedicated `inset-0` decoration layer *inside* `.card`, not on
 * `.card`'s own class list -- visually identical (same edges, same rounded corners), but a
 * background-color contributed here now genuinely layers over `.card`'s own guaranteed-opaque
 * `bg-base-100` instead of competing with it for the same CSS property (whichever ends up later
 * in Tailwind's generated stylesheet otherwise wins outright, leaving `.card` only as opaque as
 * the *last* declared `background-color` utility -- harmless while nothing but the plain page
 * background ever sat behind the card, but it stopped being harmless the moment `card.blade.php`
 * grew a glowing `aura` wrapper behind it: a `bg-{color}/5` contributed here would let that glow
 * bleed straight through the "card" instead of staying a thin ring around it). See
 * `card.blade.php`'s own comment for the full layering.
 */
class RenderingCard
{
    /**
     * @var array<int, string>
     */
    public array $classes = [];

    public function __construct(public Context $context)
    {
    }

    public function addClass(string $class): self
    {
        $this->classes[] = $class;

        return $this;
    }
}
