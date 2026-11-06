<?php

declare(strict_types=1);

namespace Tests\Fixtures\Extensions\McpToolsetDeclarer;

use Kopling\Mcp\Tool;
use Kopling\OAuth\Person;
use Laravel\Mcp\Response;

class AdminThing extends Tool
{
    protected function allows(Person $person): bool
    {
        return $person->hasPermission('kopling-core::manage-people');
    }

    public function handle(): Response
    {
        return Response::text('Admin thing done');
    }
}
