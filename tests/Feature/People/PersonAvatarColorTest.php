<?php

declare(strict_types=1);

use Kopling\Core\People\Person;

it('gives the same person the same avatar color every time', function () {
    $person = Person::create(['name' => 'Ada Lovelace', 'email' => 'ada@example.test', 'password' => 'secret']);

    expect($person->avatarColor())->toBe($person->avatarColor());
});

it('gives two different people different seeds, so their colors are independent of order', function () {
    $ada = Person::create(['name' => 'Ada Lovelace', 'email' => 'ada@example.test', 'password' => 'secret']);
    $grace = Person::create(['name' => 'Grace Hopper', 'email' => 'grace@example.test', 'password' => 'secret']);

    expect($ada->avatarColor())->toBe(Person::colorFor($ada->id))
        ->and($grace->avatarColor())->toBe(Person::colorFor($grace->id))
        ->and($ada->avatarColor())->not->toBe($grace->avatarColor());
});

it('colorFor() is a plain deterministic function of its seed, usable with no Person at all', function () {
    expect(Person::colorFor('same-seed'))->toBe(Person::colorFor('same-seed'))
        ->and(Person::colorFor('a'))->toMatch('/^hsl\(\d+deg 45% 45%\)$/');
});
