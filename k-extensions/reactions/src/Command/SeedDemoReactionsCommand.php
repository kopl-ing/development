<?php

declare(strict_types=1);

namespace Kopling\Reactions\Command;

use Illuminate\Console\Command;
use Kopling\Core\Content\Moment;
use Kopling\Core\People\Person;
use Kopling\Reactions\Reaction;

/**
 * Sprinkles emoji reactions -- some carrying a word, so the "Latest reactions" strip has
 * something to show -- across moments, from a spread of people. The companion to the demo
 * ext's fake-data seeder and discussions' reply seeder, so a fresh install's feed reads as
 * a lively community rather than empty cards. Idempotent per (moment, person, emoji), so
 * re-running only tops up.
 */
class SeedDemoReactionsCommand extends Command
{
    protected $signature = 'kopling:reactions:seed-demo';

    protected $description = 'Seed emoji + worded reactions across moments';

    public function handle(): int
    {
        $people = Person::query()->get();

        if ($people->isEmpty()) {
            $this->warn('No people to author reactions -- run kopling:demo:seed-fake-data first.');

            return self::FAILURE;
        }

        $words = ['love this', 'so true', 'brilliant', 'underrated', 'yes!', 'this', 'well said', 'inspiring', 'same energy', 'wholesome'];
        $total = 0;

        Moment::query()->get()->each(function (Moment $moment) use ($people, $words, &$total) {
            $reactors = $people->shuffle()->take(random_int(1, min(6, $people->count())));

            foreach ($reactors as $person) {
                $emoji = Reaction::PALETTE[array_rand(Reaction::PALETTE)];

                $reaction = Reaction::firstOrCreate(
                    ['moment_id' => $moment->id, 'person_id' => $person->id, 'emoji' => $emoji],
                    ['word' => fake()->boolean(35) ? fake()->randomElement($words) : null],
                );

                if ($reaction->wasRecentlyCreated) {
                    $total++;
                }
            }
        });

        $this->info("Seeded {$total} reactions across ".Moment::query()->count().' moments.');

        return self::SUCCESS;
    }
}
