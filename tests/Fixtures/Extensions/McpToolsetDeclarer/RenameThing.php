<?php

declare(strict_types=1);

namespace Tests\Fixtures\Extensions\McpToolsetDeclarer;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Kopling\Mcp\Tool;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

class RenameThing extends Tool
{
    public const TEAM = '01a1b2c3-0000-7000-8000-000000000001';

    protected string $name = 'rename-thing';

    public function schema(JsonSchema $schema): array
    {
        return ['name' => $schema->string()->required()];
    }

    public function handle(Request $request): Response
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:10']]);
        $this->aboutTeam(self::TEAM);

        return Response::text('Renamed to '.$data['name']);
    }
}
