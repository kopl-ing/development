<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Kopling\Core\People\Group;
use Kopling\Mcp\Registry;
use Kopling\Mcp\ServiceProvider;
use Kopling\Mcp\ToolCall;
use Kopling\OAuth\Person;
use Kopling\OAuth\Scopes;
use Kopling\OAuth\Tokens;
use phpseclib4\Crypt\RSA;
use Tests\Fixtures\Extensions\BadMcpToolset\BadNameTool;
use Tests\Fixtures\Extensions\BadMcpToolset\Extension as BadToolset;
use Tests\Fixtures\Extensions\BadMcpToolset\LongNameTool;
use Tests\Fixtures\Extensions\McpToolsetDeclarer\RenameThing;

const MCP_FIXTURE = 'tests-fixtures-mcp-toolset-declarer';

function mcpTestKey(): RSA
{
    static $key;

    return $key ??= RSA::createKey(2048);
}

function fixtureRegistry(string $package = 'tests-fixtures/mcp-toolset-declarer', string $directory = 'McpToolsetDeclarer'): Registry
{
    return new Registry(fakeManager([
        $package => [
            'namespace' => 'Tests\\Fixtures\\Extensions\\'.$directory.'\\',
            'path' => base_path('tests/Fixtures/Extensions/'.$directory),
        ],
    ]));
}

beforeEach(function () {
    $key = mcpTestKey();
    config()->set('passport.private_key', (string) $key);
    config()->set('passport.public_key', (string) $key->getPublicKey());

    Scopes::flush();
    $registry = fixtureRegistry();
    app()->instance(Registry::class, $registry);
    ServiceProvider::registerScopes($registry);
});

afterEach(fn () => Scopes::flush());

function mcpPerson(array $permissions = ['kopling-mcp::use-mcp']): Person
{
    $person = Person::create(['name' => 'Daan', 'email' => Str::random(8).'@example.test', 'password' => 'secret']);
    if ($permissions !== []) {
        $group = Group::create(['name' => 'Group '.Str::random(6)]);
        foreach ($permissions as $permission) {
            $group->givePermissionTo($permission);
        }
        $person->groups()->attach($group);
    }

    return $person;
}

function mcpToken(Person $person, array $scopes): string
{
    Tokens::personalClient();

    return $person->createToken('test', $scopes)->accessToken;
}

function rpc($test, ?string $token, string $method, array $params = [])
{
    app('auth')->forgetGuards();
    $test = $token === null ? $test : $test->withToken($token);

    return $test->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params], ['Accept' => 'application/json, text/event-stream']);
}

function toolNames($response): array
{
    return collect($response->json('result.tools'))->pluck('name')->all();
}

it('answers a call without a token with 401 and where to authorize', function () {
    $response = rpc($this, null, 'tools/list')->assertUnauthorized();

    expect($response->headers->get('WWW-Authenticate'))->toContain('resource_metadata="'.url('/.well-known/oauth-protected-resource/mcp').'"');
});

it('describes itself and the authorization server for discovery', function () {
    $this->getJson('/.well-known/oauth-protected-resource/mcp')
        ->assertOk()
        ->assertJson(['resource' => url('/mcp'), 'authorization_servers' => [url('/')]])
        ->assertJsonFragment(['scopes_supported' => ['kopling-mcp::use-mcp', MCP_FIXTURE.'::read', MCP_FIXTURE.'::write']]);

    $this->getJson('/.well-known/oauth-authorization-server')->assertOk()->assertJson([
        'authorization_endpoint' => url('/oauth/authorize'),
        'token_endpoint' => url('/oauth/token'),
        'registration_endpoint' => url('/oauth/register'),
        'code_challenge_methods_supported' => ['S256'],
    ]);
});

it('lets a client register itself and answers with Kopling\'s scopes', function () {
    $this->postJson('/oauth/register', ['client_name' => 'Claude', 'redirect_uris' => ['https://claude.ai/api/mcp/auth_callback']])
        ->assertCreated()
        ->assertJson(['scope' => 'kopling-mcp::use-mcp '.MCP_FIXTURE.'::read '.MCP_FIXTURE.'::write', 'token_endpoint_auth_method' => 'none'])
        ->assertJsonStructure(['client_id']);
});

it('refuses a token without the use-mcp scope, or a person without the permission', function () {
    $person = mcpPerson();
    rpc($this, mcpToken($person, [MCP_FIXTURE.'::read']), 'tools/list')->assertForbidden();

    $outsider = mcpPerson([]);
    rpc($this, mcpToken($outsider, ['kopling-mcp::use-mcp', MCP_FIXTURE.'::read']), 'tools/list')->assertForbidden();
});

it('stops a still-valid token at once when the person loses use-mcp', function () {
    $person = mcpPerson();
    $token = mcpToken($person, ['kopling-mcp::use-mcp', MCP_FIXTURE.'::read']);
    rpc($this, $token, 'tools/list')->assertOk();

    $person->groups()->detach();

    rpc($this, $token, 'tools/list')->assertForbidden();
});

it('lists only the tools the token\'s scopes and the person\'s permissions allow, prefixed with the extension id', function () {
    $reader = mcpPerson();
    expect(toolNames(rpc($this, mcpToken($reader, ['kopling-mcp::use-mcp', MCP_FIXTURE.'::read']), 'tools/list')))
        ->toBe([MCP_FIXTURE.'__list-things']);

    $writer = mcpPerson();
    expect(toolNames(rpc($this, mcpToken($writer, ['kopling-mcp::use-mcp', MCP_FIXTURE.'::read', MCP_FIXTURE.'::write']), 'tools/list')))
        ->toBe([MCP_FIXTURE.'__list-things', MCP_FIXTURE.'__rename-thing']);

    $admin = mcpPerson(['kopling-mcp::use-mcp', 'kopling-core::manage-people']);
    expect(toolNames(rpc($this, mcpToken($admin, ['kopling-mcp::use-mcp', MCP_FIXTURE.'::write']), 'tools/list')))
        ->toBe([MCP_FIXTURE.'__rename-thing', MCP_FIXTURE.'__admin-thing']);
});

it('runs a tool as the person behind the token and logs it, with the team it was about', function () {
    $person = mcpPerson();
    $token = mcpToken($person, ['kopling-mcp::use-mcp', MCP_FIXTURE.'::write']);

    rpc($this, $token, 'tools/call', ['name' => MCP_FIXTURE.'__rename-thing', 'arguments' => ['name' => 'Kit']])
        ->assertOk()
        ->assertJsonPath('result.isError', false)
        ->assertJsonPath('result.content.0.text', 'Renamed to Kit');

    $call = ToolCall::sole();
    expect($call->only(['person_id', 'tool', 'team_id', 'outcome', 'arguments']))->toBe([
        'person_id' => $person->id,
        'tool' => MCP_FIXTURE.'__rename-thing',
        'team_id' => RenameThing::TEAM,
        'outcome' => 'ok',
        'arguments' => ['name' => 'Kit'],
    ])->and($call->client_id)->not->toBeNull();
});

it('logs a refused and a failed call too', function () {
    $person = mcpPerson();
    $token = mcpToken($person, ['kopling-mcp::use-mcp', MCP_FIXTURE.'::write']);

    rpc($this, $token, 'tools/call', ['name' => MCP_FIXTURE.'__list-things'])->assertJsonPath('error.code', -32602);
    rpc($this, $token, 'tools/call', ['name' => MCP_FIXTURE.'__rename-thing', 'arguments' => ['name' => 'Far too long a name']])
        ->assertJsonPath('result.isError', true);

    expect(ToolCall::orderBy('created_at')->pluck('outcome', 'tool')->all())->toBe([
        MCP_FIXTURE.'__list-things' => 'denied',
        MCP_FIXTURE.'__rename-thing' => 'error',
    ]);
});

it('refuses a tool with an invalid local name, a too long full name, or no Kopling base class', function (array $tools, string $message) {
    BadToolset::$tools = $tools;

    expect(fn () => fixtureRegistry('tests-fixtures/bad-mcp-toolset', 'BadMcpToolset')->tools())
        ->toThrow(InvalidArgumentException::class, $message);
})->with([
    'bad name' => [[BadNameTool::class], 'must be kebab case'],
    'too long' => [[LongNameTool::class], 'longer than 64'],
    'not a Kopling tool' => [[stdClass::class], 'must extend'],
]);

it('shows the tools and recent calls to people who may see the log', function () {
    $person = mcpPerson();
    rpc($this, mcpToken($person, ['kopling-mcp::use-mcp', MCP_FIXTURE.'::read']), 'tools/call', ['name' => MCP_FIXTURE.'__list-things']);

    $this->actingAs(mcpPerson(['kopling-admin::access-admin']))->get('/admin/mcp')->assertForbidden();
    $this->actingAs(mcpPerson(['kopling-admin::access-admin', 'kopling-mcp::view-tool-calls']))->get('/admin/mcp')
        ->assertOk()
        ->assertSee(MCP_FIXTURE.'__rename-thing')
        ->assertSee('Change your things')
        ->assertSee(MCP_FIXTURE.'__list-things')
        ->assertSee('done');
});
