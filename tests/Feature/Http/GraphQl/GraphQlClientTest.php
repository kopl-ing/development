<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Kopling\Core\Http\GraphQl\GraphQlClient;
use Kopling\Core\Http\GraphQl\GraphQlException;

it('posts query and variables as JSON and returns the decoded data payload', function () {
    Http::fake([
        'example.test/graphql' => Http::response(['data' => ['ping' => 'pong']]),
    ]);

    $client = new GraphQlClient('https://example.test/graphql');

    $result = $client->query('query { ping }', ['foo' => 'bar']);

    expect($result)->toBe(['ping' => 'pong']);

    Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
        return $request->url() === 'https://example.test/graphql'
            && $request['query'] === 'query { ping }'
            && $request['variables'] === ['foo' => 'bar'];
    });
});

it('sends a bearer token when one is given, and none when not', function () {
    Http::fake(['example.test/graphql' => Http::response(['data' => []])]);

    (new GraphQlClient('https://example.test/graphql', 'secret-token'))->query('query { ping }');

    Http::assertSent(fn (\Illuminate\Http\Client\Request $request) => $request->hasHeader('Authorization', 'Bearer secret-token'));

    (new GraphQlClient('https://example.test/graphql'))->query('query { ping }');

    Http::assertSent(fn (\Illuminate\Http\Client\Request $request) => ! $request->hasHeader('Authorization'));
});

it('throws GraphQlException when the response carries GraphQL-level errors', function () {
    Http::fake([
        'example.test/graphql' => Http::response(['errors' => [['message' => 'Field not found']]]),
    ]);

    $client = new GraphQlClient('https://example.test/graphql');

    expect(fn () => $client->query('query { nope }'))
        ->toThrow(GraphQlException::class, 'Field not found');
});

it('lets a non-2xx HTTP response throw Laravel\'s own RequestException', function () {
    Http::fake(['example.test/graphql' => Http::response('Server error', 500)]);

    $client = new GraphQlClient('https://example.test/graphql');

    expect(fn () => $client->query('query { ping }'))
        ->toThrow(\Illuminate\Http\Client\RequestException::class);
});
