<?php

declare(strict_types=1);

namespace Tests\Fixtures\Extensions\BadMcpToolset;

use Kopling\Mcp\Tool;
use Laravel\Mcp\Response;

class LongNameTool extends Tool
{
    protected string $name = 'a-tool-name-that-is-far-too-long-for-any-mcp-client';

    public function handle(): Response
    {
        return Response::text('');
    }
}
