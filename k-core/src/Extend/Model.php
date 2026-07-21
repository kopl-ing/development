<?php

declare(strict_types=1);

namespace Kopling\Core\Extend;

use Closure;

/**
 * One model's worth of extension (relations, casts, hooks, morph alias, link) -- the fluent
 * entry point `ExtendsModels::models()` returns instances of.
 */
class Model
{
    /**
     * @var array<string, string>
     */
    public array $casts = [];

    /**
     * @var array<array{name: string, class: class-string, method: string, constraint: array, eagerLoad: bool|callable}>
     */
    public array $relations = [];

    /**
     * @var array{route: string, parameters: array|callable, when: bool|callable}|null
     */
    public ?array $link = null;

    public ?Closure $creating = null;

    public ?Closure $saving = null;

    public ?Closure $saved = null;

    public ?string $morphAlias = null;

    public function __construct(public readonly string $model)
    {
    }

    /**
     * Registers `$alias` in `Relation::morphMap()` (never `enforceMorphMap()` -- that also flips
     * `requireMorphMap()` app-wide, throwing for *any* unmapped model's `getMorphClass()` call,
     * not just ones opted into a morph alias).
     */
    public function morphAlias(string $alias): self
    {
        $this->morphAlias = $alias;

        return $this;
    }

    public function cast(string $attribute, string $type): self
    {
        $this->casts[$attribute] = $type;

        return $this;
    }

    /**
     * Native Eloquent `creating` -- fires once, insert-only, before the row is written.
     */
    public function creating(Closure $callback): self
    {
        $this->creating = $callback;

        return $this;
    }

    /**
     * Native Eloquent `saving` -- fires before every write, insert and update alike.
     */
    public function saving(Closure $callback): self
    {
        $this->saving = $callback;

        return $this;
    }

    /**
     * Native Eloquent `saved` -- fires after every write, once a real primary key exists. Use
     * this instead of `creating()`/`saving()` for anything that needs to touch a relation (e.g.
     * syncing a pivot), and guard on `request()->has(...)` rather than defaulting a missing key,
     * since this fires on *every* save of the model, not just the one form this hook cares about.
     */
    public function saved(Closure $callback): self
    {
        $this->saved = $callback;

        return $this;
    }

    /**
     * The named route this model's cards should link out to -- read by
     * `Ux\Context::getSubjectUrl()`. `$parameters` defaults to `[$subject->getRouteKey()]`.
     */
    public function linksTo(string $route, array|callable $parameters = [], bool|callable $when = true): self
    {
        $this->link = compact('route', 'parameters', 'when');

        return $this;
    }

    /**
     * Flattens a `Relation` builder's declarations onto this model, stamping `$relation->eagerLoad`
     * onto each -- two `relation()` calls on the same `Model` can disagree on eager-loading.
     */
    public function relation(Relation $relation): self
    {
        foreach ($relation->relations as $definition) {
            $definition['eagerLoad'] = $relation->eagerLoad;

            $this->relations[] = $definition;
        }

        return $this;
    }
}
