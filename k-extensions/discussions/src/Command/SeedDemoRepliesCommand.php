<?php

declare(strict_types=1);

namespace Kopling\Discussions\Command;

use Illuminate\Console\Command;
use Kopling\Core\Content\Moment;
use Kopling\Core\People\Person;
use Kopling\Discussions\Reply;

class SeedDemoRepliesCommand extends Command
{
    protected $signature = 'kopling:discussions:seed-demo';

    protected $description = 'Seed a handful of demo replies across moments';

    public function handle(): int
    {
        $people = Person::query()->get();

        if ($people->isEmpty()) {
            $this->warn('No people to author replies -- run kopling:demo:seed-fake-data first.');

            return self::FAILURE;
        }

        $total = 0;

        Moment::query()->get()->each(function (Moment $moment) use ($people, &$total) {
            $count = random_int(0, 4);

            for ($i = 0; $i < $count; $i++) {
                Reply::create([
                    'moment_id' => $moment->id,
                    'person_id' => $people->random()->id,
                    'body' => fake()->sentence(random_int(6, 20)),
                ]);
                $total++;
            }
        });

        $this->info("Seeded {$total} replies across ".Moment::query()->count().' moments.');

        return self::SUCCESS;
    }
}
