<?php

declare(strict_types=1);

namespace Kopling\Core\Extension\Concerns;

use Kopling\Core\Extend\Permission;
use Kopling\Core\Extension\Contract\HasPermissions;

trait AggregatesPermissions
{
    /**
     * @return array<Permission>
     */
    public function permissions(): array
    {
        if (($cached = $this->cache->get()) !== null) {
            return array_map(fn (array $data) => Permission::fromArray($data), $cached['permissions']);
        }

        $permissions = [];

        foreach ($this->extensions() as $package => $extension) {
            if (! $extension instanceof HasPermissions) {
                continue;
            }

            $declared = collect($extension->permissions())->ensure(Permission::class);

            foreach ($declared as $permission) {
                $permission->id = $this->id($package).'::'.$permission->id;

                $permissions[] = $permission;
            }
        }

        return $permissions;
    }
}
