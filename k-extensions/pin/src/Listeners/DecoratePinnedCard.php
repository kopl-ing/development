<?php

declare(strict_types=1);

namespace Kopling\Pin\Listeners;

use Illuminate\Support\Facades\Auth;
use Kopling\Core\Content\Moment;
use Kopling\Core\Ux\Card\Event\RenderingCard;

/**
 * Appends a reason-colored border to a pinned Moment's card. Reads `$moment->pin` (the magic
 * relation accessor, not `getRelation()`) so it works whether or not the relation was already
 * eager-loaded: the main feed's paginator eager-loads it for every card in one batch (see
 * `Extension::models()`), but a moment freshly prepended by the live poll
 * (`LatestMomentsController::load()`) never goes through that eager-load path, so this would
 * throw on an unloaded relation otherwise -- the extra lazy query only ever hits that rare,
 * one-at-a-time case, never the whole feed.
 */
class DecoratePinnedCard
{
    public function __invoke(RenderingCard $event): void
    {
        $moment = $event->context->getSubject();

        if (! $moment instanceof Moment) {
            return;
        }

        $pin = $moment->pin;

        if ($pin?->isVisibleTo(Auth::user())) {
            $event->addClass("border-{$pin->color()} bg-{$pin->color()}/5");
        }
    }
}
