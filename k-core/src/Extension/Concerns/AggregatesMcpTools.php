<?php

declare(strict_types=1);

namespace Kopling\Core\Extension\Concerns;

use Kopling\Core\Extend\Mcp\Toolset;
use Kopling\Core\Extension\Contract\HasMcpTools;

trait AggregatesMcpTools
{
    /**
     * Uncached: only `kopling/mcp` reads these, on its own requests.
     *
     * @return array<Toolset>
     */
    public function mcpToolsets(): array
    {
        $toolsets = [];

        foreach ($this->extensions() as $package => $extension) {
            if (! $extension instanceof HasMcpTools) {
                continue;
            }

            $id = $this->id($package);

            foreach (collect($extension->mcpTools())->ensure(Toolset::class) as $toolset) {
                $toolset->extension = $id;
                $toolset->id = $id.'::'.$toolset->id;
                $toolset->scopes = collect($toolset->scopes)->mapWithKeys(fn (array $scope, string $local) => [$id.'::'.$local => $scope])->all();

                $toolsets[] = $toolset;
            }
        }

        return $toolsets;
    }
}
