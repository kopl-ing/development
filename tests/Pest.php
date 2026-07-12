<?php

use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kopling\Core\Extension\Manager;
use Tests\Support\FakeManifest;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * A `Manager` wired to a `FakeManifest` instead of the real one -- entirely standalone, no
 * Laravel app booted -- so extensibility-mechanism tests (portals/portalExtensions/permissions/
 * ux/models) can control exactly which extensions exist without a real Composer package per
 * fixture. `Manager` always prepends real `Core` regardless (see `Manager::extensions()`), so
 * assertions should check for a fixture's own entries rather than asserting an exact, exhaustive
 * set.
 *
 * @param  array<string, array{namespace: string, path: string}>  $extensions
 */
function fakeManager(array $extensions = []): Manager
{
    return new Manager(new FakeManifest($extensions), new Dispatcher());
}
