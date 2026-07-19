<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Tests\Fixtures\Extensions\ModelHooker\Extension as ModelHookerExtension;
use Tests\Fixtures\Extensions\ModelHooker\Message;

function migrateModelHookerFixture(): void
{
    Schema::create('fixture_messages', function ($table) {
        $table->id();
        $table->string('body')->nullable();
        $table->string('ip')->nullable();
    });

    fakeManager([
        'tests-fixtures/model-hooker' => [
            'namespace' => 'Tests\\Fixtures\\Extensions\\ModelHooker\\',
            'path' => __DIR__,
        ],
    ])->models();
}

it('registers a fixture extension\'s creating/saving hooks onto its target model', function () {
    migrateModelHookerFixture();

    $message = Message::create(['body' => 'hello']);

    expect($message->fresh())
        ->ip->toBe('127.0.0.1') // creating() injected a column the caller never set
        ->body->toBe('HELLO');  // saving() transformed body on insert

    $message->update(['body' => 'again']);

    expect($message->fresh()->body)->toBe('AGAIN'); // saving() also fires on update

    $rejected = Message::create(['body' => 'reject-me']);

    expect($rejected->exists)->toBeFalse(); // a creating() hook returning false cancels the insert
});

it('registers a fixture extension\'s saved() hook, firing post-insert with a real key, and again on update', function () {
    migrateModelHookerFixture();
    ModelHookerExtension::$savedLog = [];

    $message = Message::create(['body' => 'hello']);

    expect(ModelHookerExtension::$savedLog)->toHaveCount(1)
        // Unlike creating(), which runs before the insert, saved() sees the real assigned key.
        ->and(ModelHookerExtension::$savedLog[0]['id'])->toBe($message->id)
        ->and(ModelHookerExtension::$savedLog[0]['wasRecentlyCreated'])->toBeTrue();

    // A fresh instance, not the one still held from create() -- wasRecentlyCreated stays true
    // for an object's own lifetime once set, regardless of later saves on that same instance.
    $message->fresh()->update(['body' => 'again']);

    expect(ModelHookerExtension::$savedLog)->toHaveCount(2)
        ->and(ModelHookerExtension::$savedLog[1]['id'])->toBe($message->id)
        ->and(ModelHookerExtension::$savedLog[1]['wasRecentlyCreated'])->toBeFalse();
});
