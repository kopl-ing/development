<?php

declare(strict_types=1);

namespace Kopling\Reactions\Listeners;

use Kopling\Core\Content\Event\QueryingMoments;

/**
 * Reorders the feed query by thumbs-up count when `?sort=top` is requested -- the "Top" sort
 * mode from the roadmap. Ordering by upvote count only (not net of downvotes), matching the
 * roadmap's own wording. Pulls the distinct configured `upvote_emoji` values across every tag
 * as a single global aggregate rather than branching per-moment/per-tag -- acceptable since
 * realistically only one or two tags will ever carry voting (see decisions.md).
 *
 * Same `QueryingMoments`/`ListensToEvents` mechanism Pin already uses for
 * `ExcludeVisiblePinnedMoments` -- no core change needed. Soft-dependent on
 * `Kopling\Tags\Tag` (guarded by `class_exists`), same convention `Reaction::voteConfigFor`
 * already established.
 */
class SortMomentsByVotes
{
    public function __invoke(QueryingMoments $event): void
    {
        if (request()->query('sort') !== 'top') {
            return;
        }

        if (! class_exists(\Kopling\Tags\Tag::class)) {
            return;
        }

        $upvoteEmoji = \Kopling\Tags\Tag::query()->whereNotNull('upvote_emoji')->distinct()->pluck('upvote_emoji');

        if ($upvoteEmoji->isEmpty()) {
            return;
        }

        $event->query->reorder()
            ->withCount(['reactions as votes_count' => fn ($query) => $query->whereIn('emoji', $upvoteEmoji)])
            ->orderByDesc('votes_count')
            ->orderByDesc('created_at');
    }
}
