<?php

declare(strict_types=1);

namespace Kopling\Core\Ux;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Kopling\Core\Extend\Relation;
use Kopling\Core\Extension\Manager;
use Kopling\Core\People\Person;
use Kopling\Core\Portal\Portal;

/**
 * The render-time binding a component tree resolves against -- `$subject` is whatever model
 * the tree is actually about (a `Moment`, later something else), `$actor` is whoever's
 * looking at it (`null` for a guest). Passed down unchanged from a tree's root all the way
 * to its leaves, and carried on every `UxEntry` a slot resolves, so a registered component
 * never needs anything threaded through as a loose, positional array -- it reads
 * `$context->subject`/`$context->actor` directly.
 *
 * `$subject` is deliberately `mixed`, not typed to `Moment` -- there's only one bound-model
 * type in the codebase today, so typing this against a shared interface would be inventing
 * structure ahead of a real second need.
 */
class Context
{
    public function __construct(
        protected Builder|Model|null $subject = null,
        public ?Portal             $portal = null,
        public ?Request            $request = null,
        public ?Person             $actor = null,
    ) {
        $this->actor ??= Auth::user();
    }

    public function getSubject(bool $fail = false): Model
    {
        if ($this->subject instanceof Model) {
            return $this->subject;
        }

        return $fail
            ? $this->getSubjectQuery()->firstOrFail()
            : $this->getSubjectQuery()->first();
    }

    public function getSubjects(): Collection
    {
        return $this->getSubjectQuery()->get();
    }

    public function getSubjectPaginator(): LengthAwarePaginator
    {
        return $this->getSubjectQuery()->paginate();
    }

    protected function getSubjectQuery(): Builder
    {
        throw_unless($this->subject, 'No subject set on context.');
        throw_unless($this->subject instanceof Builder, 'Subject must be a query builder.');

        /** @var Manager $manager */
        $manager = resolve(Manager::class);

        $manager->relations()
            ->where('model', $this->subject->getModel()->getMorphClass())
            ->pluck('relations')
            ->flatten(1)
            ->keyBy('name')
            ->each(fn (array $definition, string $relation) => $this->subject = $this->subject->with($relation));

        return $this->subject;
    }
}
