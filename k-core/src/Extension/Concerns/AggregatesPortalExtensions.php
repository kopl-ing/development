<?php

declare(strict_types=1);

namespace Kopling\Core\Extension\Concerns;

use Illuminate\Support\Collection;
use Kopling\Core\Extension\Contract\ExtendsPortals;
use Kopling\Core\Portal\PortalExtension;

trait AggregatesPortalExtensions
{
    /**
     * Grouped by target Portal id. Targeting a Portal id that isn't registered is a silent
     * no-op, same as a dangling `ux()` `after`/`before` reference.
     *
     * @return Collection<string, Collection<int, PortalExtension>>
     */
    public function portalExtensions(): Collection
    {
        if (($cached = $this->cache->get()) !== null) {
            return collect($cached['portalExtensions'])->map(
                fn (array $group) => collect($group)->map(fn (array $data) => PortalExtension::fromArray($data))
            );
        }

        $extensions = [];

        foreach ($this->extensions() as $extension) {
            if (! $extension instanceof ExtendsPortals) {
                continue;
            }

            $declared = collect($extension->extendsPortals())->ensure(PortalExtension::class);

            foreach ($declared as $portalExtension) {
                $extensions[$portalExtension->portal][] = $portalExtension;
            }
        }

        return collect($extensions)->map(fn (array $group) => collect($group));
    }
}
