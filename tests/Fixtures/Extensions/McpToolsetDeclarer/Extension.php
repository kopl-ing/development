<?php

declare(strict_types=1);

namespace Tests\Fixtures\Extensions\McpToolsetDeclarer;

use Kopling\Core\Extend\Mcp\Toolset;
use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\HasMcpTools;

class Extension extends AbstractExtension implements HasMcpTools
{
    public static function name(): string
    {
        return 'MCP Toolset Declarer Fixture';
    }

    public static function description(): string
    {
        return 'Declares a read and a write scope, for testing kopling/mcp.';
    }

    public function mcpTools(): array
    {
        return [
            (new Toolset('things'))
                ->label('Things')
                ->scope('read', 'See your things', [ListThings::class])
                ->scope('write', 'Change your things', [RenameThing::class, AdminThing::class]),
        ];
    }
}
