<?php

declare(strict_types=1);

namespace Kopling\Core\Extend;

use Exception;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Relation
{
    public ?string $model = null;
    public array $relations = [];
    public mixed $eagerLoad = null;

    public function for(string $model): self
    {
        if ($this->model !== null) {
            throw new Exception('Model to extend with relationships already set: '.$this->model);
        }

        $this->model = $model;

        return $this;
    }

    public function hasOne(string $relation, ...$args): self
    {
        $this->relations[$relation] = ['class' => HasOne::class, 'constraint' => $args];

        return $this;
    }

    public function hasOneThrough(string $relation, ...$args): self
    {
        $this->relations[$relation] = ['class' => HasOneThrough::class, 'constraint' => $args];

        return $this;
    }

    public function morphOne(string $relation, ...$args): self
    {
        $this->relations[$relation] = ['class' => MorphOne::class, 'constraint' => $args];

        return $this;
    }

    public function belongsTo(string $relation, ...$args): self
    {
        $this->relations[$relation] = ['class' => BelongsTo::class, 'constraint' => $args];

        return $this;
    }

    public function morphTo(string $relation, ...$args): self
    {
        $this->relations[$relation] = ['class' => MorphTo::class, 'constraint' => $args];

        return $this;
    }

    public function hasMany(string $relation, ...$args): self
    {
        $this->relations[$relation] = ['class' => HasMany::class, 'constraint' => $args];

        return $this;
    }

    public function hasManyThrough(string $relation, ...$args): self
    {
        $this->relations[$relation] = ['class' => HasManyThrough::class, 'constraint' => $args];

        return $this;
    }

    public function morphMany(string $relation, ...$args): self
    {
        $this->relations[$relation] = ['class' => MorphMany::class, 'constraint' => $args];

        return $this;
    }

    public function belongsToMany(string $relation, ...$args): self
    {
        $this->relations[$relation] = ['class' => BelongsToMany::class, 'constraint' => $args];

        return $this;
    }

    public function morphToMany(string $relation, ...$args): self
    {
        $this->relations[$relation] = ['class' => MorphToMany::class, 'constraint' => $args];

        return $this;
    }

    public function morphedByMany(string $relation, ...$args): self
    {
        $this->relations[$relation] = ['class' => MorphToMany::class, 'constraint' => $args];

        return $this;
    }

    public function eagerLoad(bool|callable $when = true): self
    {
        $this->eagerLoad = $when;

        return $this;
    }
}
