<?php

declare(strict_types=1);

namespace Kopling\Core\Content\Event;

use Illuminate\Database\Eloquent\Builder;

/**
 * Dispatched everywhere the feed queries `Moment` (`IndexController`, `LatestMomentsController`)
 * right before the query runs, so an extension can filter it in place -- `$query` is mutated
 * directly (Eloquent's own query methods already mutate `$this` and return it), no reassignment
 * needed. Same mutable-event shape `Authentication\Event\AttemptLogin` already uses, wired
 * through the same `ListensToEvents`/`Manager::listeners()` mechanism -- not a new contract.
 */
class QueryingMoments
{
    public function __construct(public Builder $query)
    {
    }
}
