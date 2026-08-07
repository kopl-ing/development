<?php

declare(strict_types=1);

namespace Kopling\Core\Extension\Concerns;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\Relation as EloquentRelation;
use Illuminate\Support\Collection;
use Kopling\Core\Database\Model as DatabaseModel;
use Kopling\Core\Extend\Model as ExtendModel;
use Kopling\Core\Extension\Contract\ExtendsModels;

trait AggregatesModels
{
    protected ?Collection $models = null;

    /**
     * Applies every extension's relations/hooks/casts/morph-map as a side effect (not a pure
     * aggregation) -- cached on the instance so this only ever runs once per request.
     */
    public function models(): Collection
    {
        if ($this->models !== null) {
            return $this->models;
        }

        $declared = collect();

        foreach ($this->extensions() as $extension) {
            if (! $extension instanceof ExtendsModels) {
                continue;
            }

            $declared->push(...$extension->models());
        }

        $declared->ensure(ExtendModel::class);

        $casts = [];
        $perPages = [];

        $declared->each(function (ExtendModel $model) use (&$casts, &$perPages) {
            if (! class_exists($model->model)) {
                return;
            }

            /** @var class-string<EloquentModel> $class */
            $class = $model->model;

            foreach ($model->relations as $definition) {
                $class::resolveRelationUsing(
                    $definition['name'],
                    function (EloquentModel $instance) use ($definition) {
                        return $instance->{$definition['method']}(...$definition['constraint']);
                    }
                );
            }

            if ($model->creating !== null) {
                $class::creating($model->creating);
            }

            if ($model->saving !== null) {
                $class::saving($model->saving);
            }

            if ($model->saved !== null) {
                $class::saved($model->saved);
            }

            if ($model->morphAlias !== null) {
                EloquentRelation::morphMap([$model->morphAlias => $class]);
            }

            $casts[$model->model] = array_merge($casts[$model->model] ?? [], $model->casts);

            if ($model->perPage !== null) {
                $perPages[$model->model] = $model->perPage;
            }
        });

        DatabaseModel::registerCasts($casts);
        DatabaseModel::registerPerPage($perPages);

        $this->models = $declared;

        return $declared;
    }
}
