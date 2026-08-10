<?php

declare(strict_types=1);

namespace Kopling\Core\Extension\Concerns;

use Illuminate\Support\Collection;
use Kopling\Core\Extend\Federation;
use Kopling\Core\Extension\Contract\HasFederatedModels;

trait AggregatesFederatedModels
{
    protected ?Collection $federatedModels = null;

    /**
     * Instance-cached per-request, same as `AggregatesModels::models()` and for the same reason:
     * `Federation::$toActivity`/`$fromActivity` are closures, which can't survive `var_export()`,
     * so this can never be routed through `RegistrationCache` the way
     * `portals()`/`permissions()`/`modelValidationRules()` are.
     *
     * @return Collection<int, Federation>
     */
    public function federatedModels(): Collection
    {
        if ($this->federatedModels !== null) {
            return $this->federatedModels;
        }

        $declared = collect();

        foreach ($this->extensions() as $extension) {
            if (! $extension instanceof HasFederatedModels) {
                continue;
            }

            $declared->push(...$extension->federatedModels());
        }

        return $this->federatedModels = $declared->ensure(Federation::class);
    }
}
