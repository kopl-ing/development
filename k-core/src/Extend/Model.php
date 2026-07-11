<?php

declare(strict_types=1);

namespace Kopling\Core\Extend;

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

    public function __construct(public readonly string $model)
    {
    }

    public function cast(string $attribute, string $type): self
    {
        $this->casts[$attribute] = $type;

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
