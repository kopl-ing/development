<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Card\Event;

use Kopling\Core\Ux\Context;

/**
 * Dispatched once per `Card` render (see `Card`'s own constructor) so an extension can append a
 * class to the card's outer wrapper -- e.g. a colored border for a pinned Moment. `$classes`
 * accumulates rather than replaces, so more than one listener can each contribute independently
 * without needing to know about the others. Same mutable-event shape as
 * `Content\Event\QueryingMoments`/`Authentication\Event\AttemptLogin`, wired through the same
 * `ListensToEvents`/`Manager::listeners()` mechanism.
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
