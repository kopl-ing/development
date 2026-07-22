<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Kopling\Core\Ux\Form\IconSearch\FontAwesomeIconSearch;

it('queries the FA GraphQL API and returns id/label/icon for each resolvable result', function () {
    Http::fake([
        'api.fontawesome.com' => Http::response([
            'data' => [
                'searchPaginated' => [
                    'icons' => [
                        ['id' => 'star', 'label' => 'Star'],
                        ['id' => 'house', 'label' => 'House'],
                    ],
                ],
            ],
        ]),
    ]);

    $results = (new FontAwesomeIconSearch)->search('star');

    expect($results)->toHaveCount(2)
        ->and($results[0]['id'])->toBe('star')
        ->and($results[0]['label'])->toBe('Star')
        ->and($results[0]['icon'])->toContain('<svg')
        ->and($results[1]['id'])->toBe('house');

    Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
        return $request->url() === 'https://api.fontawesome.com'
            && $request['variables']['query'] === 'star';
    });
});

it('silently skips a result id that has no solid-style icon locally', function () {
    Http::fake([
        'api.fontawesome.com' => Http::response([
            'data' => [
                'searchPaginated' => [
                    'icons' => [
                        ['id' => 'star', 'label' => 'Star'],
                        ['id' => 'not-a-real-icon-name', 'label' => 'Bogus'],
                    ],
                ],
            ],
        ]),
    ]);

    $results = (new FontAwesomeIconSearch)->search('star');

    expect($results)->toHaveCount(1)
        ->and($results[0]['id'])->toBe('star');
});

it('returns an empty array when the API returns no icons', function () {
    Http::fake([
        'api.fontawesome.com' => Http::response(['data' => ['searchPaginated' => ['icons' => []]]]),
    ]);

    expect((new FontAwesomeIconSearch)->search('zzzznotarealterm'))->toBe([]);
});

it('GET /_xhr/kopling-core/icon-search returns [] for a blank query without hitting the API at all', function () {
    Http::fake();

    $this->get('/_xhr/kopling-core/icon-search?q=')->assertOk()->assertExactJson([]);

    Http::assertNothingSent();
});

it('GET /_xhr/kopling-core/icon-search proxies a real term through to search results', function () {
    Http::fake([
        'api.fontawesome.com' => Http::response([
            'data' => ['searchPaginated' => ['icons' => [['id' => 'star', 'label' => 'Star']]]],
        ]),
    ]);

    $this->get('/_xhr/kopling-core/icon-search?q=star')
        ->assertOk()
        ->assertJsonFragment(['id' => 'star', 'label' => 'Star']);
});
