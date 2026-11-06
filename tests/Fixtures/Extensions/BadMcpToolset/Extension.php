<?php

declare(strict_types=1);

namespace Tests\Fixtures\Extensions\BadMcpToolset;

use Kopling\Core\Extend\Mcp\Toolset;
use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\HasMcpTools;

class Extension extends AbstractExtension implements HasMcpTools
{
    public static array $tools = [];

    public static function name(): string
    {
        return 'Bad MCP Toolset Fixture';
    }

    public static function description(): string
    {
        return 'Declares whatever tools a test puts in $tools.';
    }

    public function mcpTools(): array
    {
        return [(new Toolset('bad'))->scope('read', 'Bad', self::$tools)];
    }
}
