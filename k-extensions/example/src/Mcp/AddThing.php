<?php

declare(strict_types=1);

namespace Kopling\Example\Mcp;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Kopling\Mcp\Tool;
use Kopling\OAuth\Person;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

class AddThing extends Tool
{
    protected string $name = 'add-thing';

    protected string $description = 'Adds a thing with the given name.';

    protected function allows(Person $person): bool
    {
        return $person->hasPermission('kopling-example::manage-things');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('The name of the new thing.')->required(),
        ];
    }

    public function handle(Request $request): Response
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);

        DB::table('example_things')->insert(['id' => (string) Str::uuid7(), 'name' => $data['name'], 'created_at' => now(), 'updated_at' => now()]);

        return Response::text('Added '.$data['name'].'.');
    }
}
