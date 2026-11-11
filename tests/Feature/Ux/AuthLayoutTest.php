<?php

declare(strict_types=1);

use Kopling\Core\Extension\Manager;

it('renders the login, registration and recovery pages through the shared auth layout', function (string $path, string $page) {
    $this->get($path)->assertOk()->assertSee('data-kopling-auth="'.$page.'"', false);
})->with([
    ['/login', 'login'],
    ['/register', 'register'],
    ['/forgot-password', 'forgot-password'],
    ['/access-blocked', 'access-blocked'],
]);

it('renders what extensions add to its header, aside and footer slots, in that order around the card', function () {
    app()->instance(Manager::class, fakeManager([
        'tests-fixtures/auth-layout-entry' => [
            'namespace' => 'Tests\\Fixtures\\Extensions\\AuthLayoutEntry\\',
            'path' => __DIR__.'/../../Fixtures/Extensions/AuthLayoutEntry',
        ],
    ]));

    $html = (string) $this->blade('<x-k::portal.auth-layout page="login" title="Fixture Title" class="fixture-class">Fixture Body</x-k::portal.auth-layout>');

    expect($html)->toContain('fixture-class')
        ->and(strpos($html, 'Fixture Header'))->toBeLessThan(strpos($html, 'Fixture Title'))
        ->and(strpos($html, 'Fixture Title'))->toBeLessThan(strpos($html, 'Fixture Body'))
        ->and(strpos($html, 'Fixture Body'))->toBeLessThan(strpos($html, 'Fixture Aside'))
        ->and(strpos($html, 'Fixture Aside'))->toBeLessThan(strpos($html, 'Fixture Footer'));
});
