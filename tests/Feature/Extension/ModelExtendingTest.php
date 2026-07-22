<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Tests\Fixtures\Extensions\ModelExtender\Gadget;
use Tests\Fixtures\Extensions\ModelExtender\Widget;

function migrateModelExtenderFixtures(): void
{
    Schema::create('fixture_gadgets', function ($table) {
        $table->id();
        $table->text('metadata')->nullable();
    });

    Schema::create('fixture_parts', function ($table) {
        $table->id();
        $table->foreignId('gadget_id');
        $table->string('name');
    });

    Schema::create('fixture_widgets', function ($table) {
        $table->id();
        $table->text('notes')->nullable();
    });
}

it('registers a fixture extension\'s relation and cast onto its target model', function () {
    migrateModelExtenderFixtures();

    fakeManager([
        'tests-fixtures/model-extender' => [
            'namespace' => 'Tests\\Fixtures\\Extensions\\ModelExtender\\',
            'path' => __DIR__,
        ],
    ])->models();

    $gadget = Gadget::create(['metadata' => ['color' => 'blue']]);
    $gadget->parts()->create(['name' => 'bolt']);

    $fresh = Gadget::find($gadget->id);

    expect($fresh->metadata)->toBe(['color' => 'blue']) // the registered cast applied
        ->and($fresh->parts)->toHaveCount(1)             // resolveRelationUsing() actually resolves rows
        ->and($fresh->parts->first()->name)->toBe('bolt');
});

it('applies a registered cast to a model that only uses HasExtendedCasts directly, same as Person', function () {
    migrateModelExtenderFixtures();

    fakeManager([
        'tests-fixtures/model-extender' => [
            'namespace' => 'Tests\\Fixtures\\Extensions\\ModelExtender\\',
            'path' => __DIR__,
        ],
    ])->models();

    $widget = Widget::create(['notes' => ['todo' => 'ship it']]);

    expect(Widget::find($widget->id)->notes)->toBe(['todo' => 'ship it']);
});

it('keeps each target model\'s registered casts isolated from the other, despite sharing one registry', function () {
    migrateModelExtenderFixtures();

    fakeManager([
        'tests-fixtures/model-extender' => [
            'namespace' => 'Tests\\Fixtures\\Extensions\\ModelExtender\\',
            'path' => __DIR__,
        ],
    ])->models();

    expect(Gadget::make()->getCasts())->toHaveKey('metadata')
        ->and(Gadget::make()->getCasts())->not->toHaveKey('notes')
        ->and(Widget::make()->getCasts())->toHaveKey('notes')
        ->and(Widget::make()->getCasts())->not->toHaveKey('metadata');
});

it('lets a registered perPage() override win over the model\'s own declared default', function () {
    migrateModelExtenderFixtures();

    fakeManager([
        'tests-fixtures/model-extender' => [
            'namespace' => 'Tests\\Fixtures\\Extensions\\ModelExtender\\',
            'path' => __DIR__,
        ],
    ])->models();

    expect(Gadget::make()->getPerPage())->toBe(5)
        // Widget never gets a perPage() declaration -- still falls back to Eloquent's own
        // built-in default, proving the override is opt-in per model, not a blanket change.
        ->and(Widget::make()->getPerPage())->toBe(15);
});
