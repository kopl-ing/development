<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Kopling\Core\Extension\Manager;
use Kopling\Core\Ux\Context;
use Tests\Fixtures\Extensions\ModelExtender\Gadget;

/*
 * `Context::getSubjectUrl()` reads whatever `linksTo()` declaration `Manager::models()` carries
 * for the subject's model class -- these swap the real, container-bound singleton for a
 * `fakeManager()` instance built from disposable fixtures (see tests/Pest.php), the same approach
 * ThemeTest.php and ModelExtendingTest.php already use, so a real extension being installed or
 * not can't affect the assertions.
 */

beforeEach(function () {
    Schema::create('fixture_gadgets', function ($table) {
        $table->id();
        $table->text('metadata')->nullable();
    });

    Route::get('/fixture-gadgets/{gadget}', fn () => '')->name('fixture-gadgets.show');
    Route::get('/fixture-gadgets/{gadget}/other', fn () => '')->name('fixture-gadgets.other');

    // Route::name() only updates the Route instance's own action array -- the router's name
    // lookup table is normally rebuilt once, after an entire routes file finishes loading
    // (RouteServiceProvider::loadRoutes()); registering routes ad hoc mid-test bypasses that, so
    // route()/URL generation can't find them by name without this.
    app('router')->getRoutes()->refreshNameLookups();
});

function swapLinkedModels(array $extensions): void
{
    app()->instance(Manager::class, fakeManager($extensions));
}

it('getSubjectUrl() is null when no extension declared a link for the subject\'s model', function () {
    swapLinkedModels([]);

    $gadget = Gadget::create();

    expect((new Context(subject: $gadget))->getSubjectUrl())->toBeNull();
});

it('getSubjectUrl() resolves the route an extension declared via linksTo()', function () {
    swapLinkedModels([
        'tests-fixtures/model-linker' => [
            'namespace' => 'Tests\\Fixtures\\Extensions\\ModelLinker\\',
            'path' => __DIR__,
        ],
    ]);

    $gadget = Gadget::create();

    expect((new Context(subject: $gadget))->getSubjectUrl())
        ->toBe(route('fixture-gadgets.show', $gadget->id));
});

it('getSubjectUrl() is null when the declared link\'s $when evaluates false', function () {
    swapLinkedModels([
        'tests-fixtures/model-linker-conditional' => [
            'namespace' => 'Tests\\Fixtures\\Extensions\\ModelLinkerConditional\\',
            'path' => __DIR__,
        ],
    ]);

    $gadget = Gadget::create();

    expect((new Context(subject: $gadget))->getSubjectUrl())->toBeNull();
});

it('getSubjectUrl() resolves the last-registered linksTo() when two extensions collide on the same model', function () {
    swapLinkedModels([
        'tests-fixtures/model-linker' => [
            'namespace' => 'Tests\\Fixtures\\Extensions\\ModelLinker\\',
            'path' => __DIR__,
        ],
        'tests-fixtures/model-linker-override' => [
            'namespace' => 'Tests\\Fixtures\\Extensions\\ModelLinkerOverride\\',
            'path' => __DIR__,
        ],
    ]);

    $gadget = Gadget::create();

    expect((new Context(subject: $gadget))->getSubjectUrl())
        ->toBe(route('fixture-gadgets.other', $gadget->id));
});
