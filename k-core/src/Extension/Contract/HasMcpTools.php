<?php

declare(strict_types=1);

namespace Kopling\Core\Extension\Contract;

use Kopling\Core\Extend\Mcp\Toolset;

interface HasMcpTools
{
    /**
     * @return array<Toolset>
     */
    public function mcpTools(): array;
}
