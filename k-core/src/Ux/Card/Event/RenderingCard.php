<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Card\Event;

use Kopling\Core\Ux\Context;

/**
 * Dispatched once per `Card` render so an extension can append a class for the card's own
 * decoration (e.g. a colored border/wash for a pinned Moment). `$classes` accumulates rather
 * than replaces. Renders on a dedicated decoration layer inside `.card`, not `.card`'s own class
 * list -- a contributed `background-color` needs to layer over `.card`'s opaque background
 * rather than compete with it for the same CSS property; see `card.blade.php`.
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
