<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Tests\Fixtures\Extensions\ModelHooker\Message;

it('registers a fixture extension\'s creating/saving hooks onto its target model', function () {
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

    $message = Message::create(['body' => 'hello']);

    expect($message->fresh())
        ->ip->toBe('127.0.0.1') // creating() injected a column the caller never set
        ->body->toBe('HELLO');  // saving() transformed body on insert

    $message->update(['body' => 'again']);

    expect($message->fresh()->body)->toBe('AGAIN'); // saving() also fires on update

    $rejected = Message::create(['body' => 'reject-me']);

    expect($rejected->exists)->toBeFalse(); // a creating() hook returning false cancels the insert
});
