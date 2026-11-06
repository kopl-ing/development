<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Kopling\Mcp\Registry;
use Kopling\Mcp\ServiceProvider;
use Kopling\OAuth\Scopes;

beforeEach(function () {
    $key = mcpTestKey();
    config()->set('passport.private_key', (string) $key);
    config()->set('passport.public_key', (string) $key->getPublicKey());

    Scopes::flush();
    $registry = new Registry(fakeManager([
        'kopling/example' => ['namespace' => 'Kopling\\Example\\', 'path' => base_path('k-extensions/example')],
    ]));
    app()->instance(Registry::class, $registry);
    ServiceProvider::registerScopes($registry);
});

afterEach(fn () => Scopes::flush());

it('serves the example extension\'s toolset, the write tool only with manage-things', function () {
    $scopes = ['kopling-mcp::use-mcp', 'kopling-example::read', 'kopling-example::write'];

    expect(toolNames(rpc($this, mcpToken(mcpPerson(), $scopes), 'tools/list')))
        ->toBe(['kopling-example__list-things']);

    $token = mcpToken(mcpPerson(['kopling-mcp::use-mcp', 'kopling-example::manage-things']), $scopes);
    expect(toolNames(rpc($this, $token, 'tools/list')))
        ->toBe(['kopling-example__list-things', 'kopling-example__add-thing']);

    rpc($this, $token, 'tools/call', ['name' => 'kopling-example__add-thing', 'arguments' => ['name' => 'Kit']])
        ->assertJsonPath('result.isError', false);

    expect(DB::table('example_things')->pluck('name')->all())->toBe(['Kit']);
    rpc($this, $token, 'tools/call', ['name' => 'kopling-example__list-things'])
        ->assertJsonPath('result.isError', false)
        ->assertSee('Kit');
});
