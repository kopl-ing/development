<?php

declare(strict_types=1);

namespace Tests\Fixtures\Extensions\McpToolsetDeclarer;

use Kopling\Mcp\Tool;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListThings extends Tool
{
    protected string $description = 'Lists the person\'s things.';

    public function handle(): Response
    {
        return Response::text('Things of '.$this->person()->name);
    }
}
