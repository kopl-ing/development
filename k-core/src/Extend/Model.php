<?php

declare(strict_types=1);

namespace Kopling\Core\Extend;

use Closure;

/**
 * One model's worth of extension, combining relations and casts under a single target -- the
 * fluent entry point `ExtendsModels::models()` returns instances of. `Relation` no longer
 * carries its own target (`Relation::for()` was dropped) precisely so there's exactly one place
 * "which model" is declared, not two that could drift out of sync.
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
     * Registers this model under a short alias in Laravel's own polymorphic morph map
     * (`Relation::morphMap()`, applied as a side effect by `Manager::models()`) --
     * `morphTo`/`morphMany` columns then store `$alias` instead of the model's fully-qualified
     * class name, and a caller can resolve the class back from that same alias via
     * `Relation::getMorphedModel()` without ever needing a hard reference to it (see
     * `Kopling\Reactions\Reaction::resolveReactable()`, which is exactly why this exists --
     * routing a reaction to a Reply from `k-extensions/reactions` without that extension ever
     * importing `Kopling\Discussions\Reply`). Deliberately `morphMap()`, not
     * `enforceMorphMap()`: the latter also flips `Relation::requireMorphMap()` app-wide, which
     * then throws for *any* unmapped model's `getMorphClass()` call anywhere in the app --
     * including ones with nothing to do with a `morphAlias()` declaration at all (`Context::
     * getSubjectUrl()` calls `getMorphClass()` on whatever a card's subject happens to be, mapped
     * or not). `morphMap()` merges across calls the same way, so more than one `Extend\Model`
     * declaration (from different extensions, or Core) can each register their own alias
     * independently, the same "contribute, don't replace" shape every other collector in this
     * codebase already has -- it just doesn't also forbid every model that never opted in.
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
     * Registers a callback against the target model's own native Eloquent `creating` event --
     * fires once, insert-only, before the row is written. The right hook for setting a value
     * only relevant at creation time (e.g. stamping the creating request's IP onto a `Reply`) --
     * needs no base-class change on the target model, unlike `cast()` above, since `creating`/
     * `saving` are defined on Eloquent's own `HasEvents` trait, not something `Database\Model`
     * has to opt into. The closure receives the model instance being created as its only
     * argument -- fixed by Eloquent's own dispatcher, not `linksTo()`'s
     * `bool|callable(Portal, Request, Person)` shape -- reach for `request()`/`Auth::user()`
     * inside it the same way a controller would. Returning `false` cancels the create, same as
     * native Eloquent. A single nullable slot, not an accumulating list, matching `$link` above
     * -- one `Extend\Model` declaration needs at most one `creating` hook; two extensions
     * targeting the same model each get their own declaration (and their own slot), which
     * `Manager::models()` applies as two separate Eloquent listeners.
     */
    public function creating(Closure $callback): self
    {
        $this->creating = $callback;

        return $this;
    }

    /**
     * Same as `creating()` above but against Eloquent's native `saving` event -- fires on both
     * insert and update, before the row is written either way. The right hook for
     * sanitizing/transforming an attribute regardless of whether this is a new row or an edit
     * (e.g. expanding template hooks in a posted body), since it should still apply if the row
     * is later edited.
     */
    public function saving(Closure $callback): self
    {
        $this->saving = $callback;

        return $this;
    }

    /**
     * Against Eloquent's native `saved` event -- fires *after* the row is written, on both
     * insert and update, with a real primary key already assigned. `creating`/`saving` both fire
     * pre-write, before an insert has an id at all -- the wrong side of the write for anything
     * that needs to touch a *relation* on the model just created/updated (e.g. syncing a
     * many-to-many pivot from a submitted id list), since a pivot row needs the owning side's
     * real key to reference. This is that hook. Same closure/argument shape as `creating()`/
     * `saving()` otherwise -- reach for `request()`/`Auth::user()` inside it the same way a
     * controller would, and guard on `request()->has(...)` rather than defaulting a missing key
     * to empty, since `saved` fires on *every* save of the target model, not just the one form
     * this hook cares about -- an unguarded default would silently wipe the relation on any
     * unrelated save that doesn't carry that key at all.
     */
    public function saved(Closure $callback): self
    {
        $this->saved = $callback;

        return $this;
    }

    /**
     * Declares the named route this model's cards should link out to -- read by
     * `Ux\Context::getSubjectUrl()` so a card's default title rendering (`Ux\Card\Content`) can
     * wrap itself in an `<a>` without the declaring extension having to override any template.
     * `$parameters` defaults to `[$subject->getKey()]`, or takes a `callable(Model): array` for
     * routes needing more than the key. `$when` mirrors `Relation::eagerLoad()`'s
     * `bool|callable(Portal, Request, Person): bool` contract -- same evaluation rule, so there's
     * only one shape to learn for "cascades into templates, conditionally, per request."
     */
    public function linksTo(string $route, array|callable $parameters = [], bool|callable $when = true): self
    {
        $this->link = compact('route', 'parameters', 'when');

        return $this;
    }

    /**
     * Absorbs a `Relation` builder's declared relations onto this model -- flattened in
     * directly, not kept nested, so `Extension\Manager::models()` only ever has one shape
     * (`Model::$relations`) to walk regardless of how many `relation()` calls built it up.
     * `$relation->eagerLoad` is stamped onto every definition it contributes here (read by
     * `Ux\Context::getSubjectQuery()`) since that flag lives on the `Relation` chain it was
     * declared on, not on `Model` itself -- two `relation()` calls on the same `Model` can
     * disagree on whether their relations are eager-loaded.
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
