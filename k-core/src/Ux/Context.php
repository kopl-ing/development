<?php

declare(strict_types=1);

namespace Kopling\Core\Ux;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Auth;
use Kopling\Core\Extend\Model as ExtendModel;
use Kopling\Core\Extension\Manager;
use Kopling\Core\People\Guest;
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
        $this->request ??= request();
    }

    /**
     * The route this render happened under -- `null` whenever `$request` hasn't actually been
     * matched to one yet (the constructor defaults `$request` from the global `request()` helper,
     * but a request that hasn't gone through routing, or one built bare for a test, has no route
     * of its own). Reading this instead of the global `request()` helper directly is what lets
     * `isRoute()` (and any future route-shaped check) stay a Context method other components can
     * call and extensions can rely on, rather than every leaf reaching for framework globals on
     * its own.
     */
    public function getRoute(): ?Route
    {
        return $this->request?->route();
    }

    /**
     * Whether the current route's own `$parameter` is bound to this same subject -- e.g.
     * `$context->isRoute('moment')` on a discussion page is true for the very `Moment` that
     * page is about, false for any other moment's card rendered elsewhere (the feed, a rail).
     * `false` whenever there's no request, no such route parameter, it isn't a bound model, or
     * this context carries no concrete subject to compare against -- deliberately not calling
     * `getSubject()` for that last case, since a `Builder`/`null` subject would otherwise run a
     * query (or throw) just to answer what's really "no, that's not what this is").
     */
    public function isRoute(string $parameter): bool
    {
        if (! $this->subject instanceof Model) {
            return false;
        }

        $bound = $this->getRoute()?->parameter($parameter);

        return $bound instanceof Model && $bound->is($this->subject);
    }

    /**
     * `$actor` itself stays nullable -- existing callers already null-check it to mean "no one's
     * signed in" (e.g. reactions' own `$canReact = $actor !== null`), and swapping that null for
     * a real `Guest` object would silently flip every one of those checks. This is the
     * `Guest`-substituting read for callers that would rather not null-check at all -- the same
     * substitution `ServiceProvider`'s own Gate closure already makes for permission checks.
     */
    public function getActor(): Guest|Person
    {
        return $this->actor ?? new Guest();
    }

    /**
     * Whether `$person` is who's currently looking at this -- a real identity comparison
     * (`Model::is()`, matching by key/table/connection), not a truthiness check, so it's always
     * `false` against a `Guest` actor (never persisted, so it can never share a key with a real
     * `Person`) without needing its own special case here.
     */
    public function isActor(?Person $person): bool
    {
        return $person !== null && $this->getActor()->is($person);
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

    /**
     * The URL an extension declared this subject's cards should link out to, via
     * `Extend\Model::linksTo()` -- `null` if nothing was declared, or if a declared `$when`
     * evaluates false for this request/actor. Where more than one extension declares a link for
     * the same model, the last-registered one wins, the same rule `Manager::models()` already
     * applies to cast collisions.
     */
    public function getSubjectUrl(): ?string
    {
        $subject = $this->getSubject();

        $link = resolve(Manager::class)->models()
            ->filter(fn (ExtendModel $model) => $model->model === $subject->getMorphClass() && $model->link !== null)
            ->last()
            ?->link;

        if (! $link) {
            return null;
        }

        $when = $link['when'];

        if (! (is_callable($when) ? $when($this->portal, $this->request, $this->actor) : $when)) {
            return null;
        }

        $parameters = is_callable($link['parameters'])
            ? ($link['parameters'])($subject)
            : ($link['parameters'] ?: [$subject->getKey()]);

        return route($link['route'], $parameters);
    }

    protected function getSubjectQuery(): Builder
    {
        throw_unless($this->subject, 'No subject set on context.');
        throw_unless($this->subject instanceof Builder, 'Subject must be a query builder.');

        /** @var Manager $manager */
        $manager = resolve(Manager::class);

        $manager->models()
            ->where('model', $this->subject->getModel()->getMorphClass())
            ->pluck('relations')
            ->flatten(1)
            ->filter(function (array $definition) {
                if (is_bool($definition['eagerLoad'])) return $definition['eagerLoad'];

                if (is_callable($definition['eagerLoad'])) {
                    $callable = $definition['eagerLoad'];

                    return $callable(
                        $this->portal,
                        $this->request,
                        $this->actor,
                    );
                }

                return false;
            })
            ->keyBy('name')
            ->each(fn (array $definition, string $relation) => $this->subject = $this->subject->with($relation));

        return $this->subject;
    }
}
