<?php

declare(strict_types=1);

namespace Kopling\Reactions\Command;

use Illuminate\Console\Command;
use Kopling\Core\Content\Moment;
use Kopling\Core\Database\Model;
use Kopling\Core\People\Person;
use Kopling\Reactions\Reaction;

/**
 * Sprinkles emoji reactions -- some carrying a word, so the "Latest reactions" strip has
 * something to show -- across moments and, if `k-extensions/discussions` is installed, replies
 * too, from a spread of people. The companion to the demo ext's fake-data seeder and
 * discussions' reply seeder, so a fresh install's feed (and its discussion pages) read as a
 * lively community rather than empty cards. Idempotent per (reactable, person, emoji), so
 * re-running only tops up.
 */
class SeedDemoReactionsCommand extends Command
{
    protected $signature = 'kopling:reactions:seed-demo';

    protected $description = 'Seed emoji + worded reactions across moments and replies';

    public function handle(): int
    {
        $people = Person::query()->get();

        if ($people->isEmpty()) {
            $this->warn('No people to author reactions -- run kopling:demo:seed-fake-data first.');

            return self::FAILURE;
        }

        $words = ['love this', 'so true', 'brilliant', 'underrated', 'yes!', 'this', 'well said', 'inspiring', 'same energy', 'wholesome'];
        $total = 0;

        Moment::query()->get()->each(
            fn (Moment $moment) => $total += $this->reactToEach($moment, $people, $words)
        );

        // Soft-dependent on `Kopling\Discussions\Reply` (guarded by `class_exists`), same
        // convention `Reaction::voteConfigFor()` already established for `Tags`.
        if (class_exists(\Kopling\Discussions\Reply::class)) {
            \Kopling\Discussions\Reply::query()->get()->each(
                fn (\Kopling\Discussions\Reply $reply) => $total += $this->reactToEach($reply, $people, $words)
            );
        }

        $this->info("Seeded {$total} reactions across ".Moment::query()->count().' moments.');

        return self::SUCCESS;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Person>  $people
     * @param  array<int, string>  $words
     */
    protected function reactToEach(Model $reactable, \Illuminate\Support\Collection $people, array $words): int
    {
        $reactors = $people->shuffle()->take(random_int(1, min(6, $people->count())));
        $seeded = 0;

        foreach ($reactors as $person) {
            $emoji = Reaction::PALETTE[array_rand(Reaction::PALETTE)];

            $reaction = $reactable->reactions()->firstOrCreate(
                ['person_id' => $person->id, 'emoji' => $emoji],
                ['word' => fake()->boolean(35) ? fake()->randomElement($words) : null],
            );

            if ($reaction->wasRecentlyCreated) {
                $seeded++;
            }
        }

        return $seeded;
    }
}
