<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Tests\Fixtures\Extensions\ModelExtender\Gadget;

it('registers a fixture extension\'s relation and cast onto its target model', function () {
    Schema::create('fixture_gadgets', function ($table) {
        $table->id();
        $table->text('metadata')->nullable();
    });

    Schema::create('fixture_parts', function ($table) {
        $table->id();
        $table->foreignId('gadget_id');
        $table->string('name');
    });

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
