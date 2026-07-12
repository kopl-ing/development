<?php

declare(strict_types=1);

it('collects a declared artisan Command class-string, unprefixed', function () {
    $manager = fakeManager([
        'tests-fixtures/command-declarer' => [
            'namespace' => 'Tests\\Fixtures\\Extensions\\CommandDeclarer\\',
            'path' => __DIR__,
        ],
    ]);

    expect($manager->commands())->toContain(\Tests\Fixtures\Extensions\CommandDeclarer\PingCommand::class);
});

it('throws when a HasCommands extension declares something that is not an artisan Command', function () {
    $manager = fakeManager([
        'tests-fixtures/bad-command-declarer' => [
            'namespace' => 'Tests\\Fixtures\\Extensions\\BadCommandDeclarer\\',
            'path' => __DIR__,
        ],
    ]);

    expect(fn () => $manager->commands())
        ->toThrow(UnexpectedValueException::class, 'should only include Command class-strings');
});
