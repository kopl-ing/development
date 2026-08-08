<?php

declare(strict_types=1);

namespace Kopling\Core\Database\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Excludes content whose author has `visibility = 'hidden'` (a Phase 3 sanction) from everyone
 * except that author themselves -- the same self-still-sees mechanic every researched product
 * (Mastodon's Limit, Reddit's old shadowban) uses. Applied directly by whichever extension owns
 * a model with a `person()` relation and `person_id` column (`Moment` here, `Reply` in
 * `discussions`) via that model's own `booted()` -- the same "generalize it, don't bolt it on
 * per-controller" principle `SoftDeletes` already gets, and it means no new query-filtering
 * event needs wiring into an owning extension just for this.
 *
 * Deliberately NOT an undisclosed shadowban -- see the affected person's own notice, rendered
 * wherever `Auth::user()?->visibility === 'hidden'` is checked directly (a plain column read,
 * no wrapper method needed, same reasoning as `Person::isAccessBlocked()`'s own docblock).
 */
class AuthorVisibilityScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $viewerId = Auth::id();

        $builder->where(fn (Builder $query) => $query
            ->whereHas('person', fn (Builder $person) => $person->where('visibility', '!=', 'hidden'))
            ->orWhere($model->qualifyColumn('person_id'), $viewerId));
    }
}
