<?php

declare(strict_types=1);

namespace Kopling\Core\Extension\Concerns;

use Illuminate\Support\Collection;
use Kopling\Core\Extension\Contract\ExtendsFederatedObjects;

trait AggregatesFederatedObjectContributions
{
    protected ?Collection $federatedObjectContributions = null;

    /**
     * Instance-cached per-request, same reason `federatedModels()` is (see its own docblock) --
     * these are closures, which can't survive `var_export()`, so this can never be routed
     * through `RegistrationCache`.
     *
     * @return Collection<class-string, array<int, \Closure>>
     */
    public function federatedObjectContributions(): Collection
    {
        if ($this->federatedObjectContributions !== null) {
            return $this->federatedObjectContributions;
        }

        $contributions = [];

        foreach ($this->extensions() as $extension) {
            if (! $extension instanceof ExtendsFederatedObjects) {
                continue;
            }

            foreach ($extension->federatedObjectContributions() as $modelClass => $closure) {
                $contributions[$modelClass][] = $closure;
            }
        }

        return $this->federatedObjectContributions = collect($contributions);
    }
}
