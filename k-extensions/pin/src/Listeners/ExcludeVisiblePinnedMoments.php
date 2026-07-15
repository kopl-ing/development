<?php

declare(strict_types=1);

namespace Kopling\Pin\Listeners;

use Illuminate\Support\Facades\Auth;
use Kopling\Core\Content\Event\QueryingMoments;
use Kopling\Pin\Pin;

/**
 * Keeps a pinned-and-visible Moment out of the regular chronological feed query -- it already
 * renders once, in the pinned section (see Ux\PinnedSection), so it must not also show up in
 * the normal feed below it.
 */
class ExcludeVisiblePinnedMoments
{
    public function __invoke(QueryingMoments $event): void
    {
        $momentIds = Pin::visibleFor(Auth::user())->pluck('moment_id');

        if ($momentIds->isNotEmpty()) {
            $event->query->whereNotIn('id', $momentIds);
        }
    }
}
