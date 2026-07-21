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
 * The render-time binding a component tree resolves against -- `$subject` is whatever model the
 * tree is about, `$actor` is whoever's looking at it (`null` for a guest). Passed down unchanged
 * from a tree's root to its leaves, carried on every `UxEntry` a slot resolves.
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
        // InjectPortal middleware shares the current Portal as a request attribute -- reading it
        // here means a Context built with no explicit $portal still knows which portal it's on.
        $this->portal ??= $this->request?->attributes->get('portal');
    }

    /**
     * `null` whenever `$request` hasn't been matched to a route yet.
     */
    public function getRoute(): ?Route
    {
        return $this->request?->route();
    }

    /**
     * Whether the current route's `$parameter` is bound to this same subject. Checks `$subject`
     * directly rather than `getSubject()`, which would otherwise run a query just to answer no.
     */
    public function isRoute(string $parameter): bool
    {
        if (! $this->subject instanceof Model) {
            return false;
        }

        $bound = $this->getRoute()?->parameter($parameter);

        return $bound instanceof Model && $bound->is($this->subject);
    }

    public function isPortal(string $id): bool
    {
        return $this->portal?->id === $id;
    }

    /**
     * `$actor` itself stays nullable -- existing callers null-check it to mean "no one's signed
     * in." This is the `Guest`-substituting read for callers that would rather not.
     */
    public function getActor(): Guest|Person
    {
        return $this->actor ?? new Guest();
    }

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
     * The URL this subject's cards link out to, via `Extend\Model::linksTo()` -- `null` if
     * nothing was declared, or `$when` evaluates false. Last-registered declaration wins on
     * collision.
     */
    public function getSubjectUrl(): ?string
    {
        $subject = $this->getSubject();

        $link = resolve(Manager::class)->models()
            ->filter(fn (ExtendModel $model) => $model->model === get_class($subject) && $model->link !== null)
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
            : ($link['parameters'] ?: [$subject->getRouteKey()]);

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
