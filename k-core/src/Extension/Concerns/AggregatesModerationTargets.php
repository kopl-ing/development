<?php

declare(strict_types=1);

namespace Kopling\Core\Extension\Concerns;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Kopling\Core\Extend\ModerationTarget;
use Kopling\Core\Extension\Contract\RegistersModerationTargets;

/**
 * Applies every extension's declared moderation targets' morph-map registration as a side
 * effect (not a pure aggregation) -- same reasoning `AggregatesModels::models()` documents for
 * its own `morphAlias()` calls -- so this is memoized on the instance, not run through the
 * flatfile `RegistrationCache` the way `AggregatesPermissions`/`AggregatesPortals` are.
 *
 * Reuses an already-registered morph alias for a target's model rather than always minting a
 * new one -- `Eloquent\Model::getMorphClass()` does a *reverse* lookup (class -> alias) against
 * the single global `Relation::$morphMap`, so if two different aliases both pointed at the same
 * class (e.g. `reactions`' own `morphAlias('moment')` and a second, independent one minted here),
 * `getMorphClass()` could only ever return one of them -- silently corrupting *every* caller
 * relying on it, including `reactions`' own `MorphMany` auto-fill on `$reactable->reactions()->
 * create()`, not just this extension's own `flaggable_type` writes. `Manager::models()` runs
 * before `moderationTargets()` in `ServiceProvider::boot()`, so any alias another extension's
 * own `ExtendsModels::models()` already registered is visible here first.
 */
trait AggregatesModerationTargets
{
    protected ?Collection $moderationTargets = null;

    /**
     * @return Collection<string, ModerationTarget>
     */
    public function moderationTargets(): Collection
    {
        if ($this->moderationTargets !== null) {
            return $this->moderationTargets;
        }

        $targets = collect();

        foreach ($this->extensions() as $package => $extension) {
            if (! $extension instanceof RegistersModerationTargets) {
                continue;
            }

            foreach ($extension->moderationTargets() as $target) {
                if (! class_exists($target->model)) {
                    continue;
                }

                $existingAlias = array_search($target->model, Relation::morphMap(), true);

                if ($existingAlias !== false) {
                    $target->alias = $existingAlias;
                } else {
                    $target->alias = $this->id($package).'-'.Str::kebab(class_basename($target->model));
                    Relation::morphMap([$target->alias => $target->model]);
                }

                $target->softDeletable = in_array(SoftDeletes::class, class_uses_recursive($target->model), true);

                $targets->put($target->alias, $target);
            }
        }

        return $this->moderationTargets = $targets;
    }
}
