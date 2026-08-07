<?php

declare(strict_types=1);

namespace Kopling\Core\Extension\Concerns;

use Illuminate\Support\Collection;
use Kopling\Core\Extension\Contract\HasPortals;
use Kopling\Core\Portal\Portal;
use Kopling\Core\Settings\Settings;

trait AggregatesPortals
{
    /**
     * @return Collection<int, Portal>
     */
    public function portals(): Collection
    {
        if (($cached = $this->cache->get()) !== null) {
            return $this->applyPortalPathOverrides(
                collect($cached['portals'])->map(fn (array $data) => Portal::fromArray($data))->keyBy('id')
            );
        }

        $portals = [];

        foreach ($this->extensions() as $package => $extension) {
            if (! $extension instanceof HasPortals) {
                continue;
            }

            $prefix = $this->id($package).'::';
            $declared = collect($extension->portals())->ensure(Portal::class);

            foreach ($declared as $portal) {
                $portal->id = $prefix.$portal->id;

                if ($portal->permission !== null) {
                    $portal->permission = $prefix.$portal->permission;
                }

                $portals[] = $portal;
            }
        }

        return $this->applyPortalPathOverrides(collect($portals)->keyBy('id'));
    }

    /**
     * A final map step, run every time `portals()` is called regardless of whether it came from
     * `RegistrationCache` or a live computation -- an admin-configured path override lives in
     * `Settings` (live, DB-backed), never baked into `RegistrationCache` (a Composer-boundary
     * snapshot only rebuilt by explicitly running `kopling:extensions:cache`). Always resolves
     * from `$portal->defaultPath`, never the portal's current `$path`, so this is safe to run
     * unconditionally even against an already-overridden value reconstructed from a stale cache.
     *
     * @param  Collection<int, Portal>  $portals
     * @return Collection<int, Portal>
     */
    protected function applyPortalPathOverrides(Collection $portals): Collection
    {
        return $portals->each(function (Portal $portal) {
            $portal->path = Settings::get("core.portal_path.{$portal->id}", $portal->defaultPath);
        });
    }
}
