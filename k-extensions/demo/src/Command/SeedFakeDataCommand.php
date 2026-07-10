<?php

declare(strict_types=1);

namespace Kopling\Demo\Command;

use Illuminate\Console\Command;
use Kopling\Core\Content\Moment;
use Kopling\Core\People\Person;

class SeedFakeDataCommand extends Command
{
    protected $signature = 'kopling:demo:seed-fake-data';
    protected $description = 'Seed fake data for the demo extension';

    public function handle(): int
    {
        $person = null;

        $resolvePerson = function () use (&$person): Person {
            return $person ??= $this->personOrNew();
        };

        $actions = [$resolvePerson];

        for ($i = 0, $count = random_int(1, 3); $i < $count; $i++) {
            $actions[] = function () use ($resolvePerson): void {
                Moment::create([
                    'person_id' => $resolvePerson()->id,
                    'title' => fake()->sentence(),
                    'body' => fake()->paragraph(),
                ]);
            };
        }

        shuffle($actions);

        foreach ($actions as $action) {
            $action();
        }

        return self::SUCCESS;
    }

    protected function personOrNew(): Person
    {
        if (Person::query()->exists() && fake()->boolean(40)) {
            return Person::query()->inRandomOrder()->first();
        }

        return Person::create([
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
        ]);
    }
}
