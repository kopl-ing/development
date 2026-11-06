<?php

declare(strict_types=1);

namespace Tests\Fixtures\Extensions\BadMcpToolset;

use Kopling\Mcp\Tool;
use Laravel\Mcp\Response;

class BadNameTool extends Tool
{
    protected string $name = 'Bad_Name';

    public function handle(): Response
    {
        return Response::text('');
    }
}
