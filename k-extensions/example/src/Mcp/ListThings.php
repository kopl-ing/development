<?php

declare(strict_types=1);

namespace Kopling\Example\Mcp;

use Illuminate\Support\Facades\DB;
use Kopling\Mcp\Tool;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListThings extends Tool
{
    protected string $name = 'list-things';

    protected string $description = 'Lists every thing, with its id and name.';

    public function handle(): Response
    {
        return Response::json(DB::table('example_things')->orderBy('name')->get(['id', 'name'])->all());
    }
}
