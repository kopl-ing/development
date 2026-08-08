<?php

declare(strict_types=1);

namespace Kopling\Moderation\Command;

use Illuminate\Console\Command;
use Kopling\Core\Content\Moment;
use Kopling\Core\Extension\Manager;
use Kopling\Core\Moderation\ModerationReason;
use Kopling\Core\People\Group;
use Kopling\Core\People\Person;
use Kopling\Moderation\Flag;

/**
 * Scoped to this extension's own permission -- not `demo`'s blanket `SeedAdminCommand` shape
 * (which grants every registered permission on purpose). Also leaves a couple of demo flags in
 * the queue against existing content, so `/moderation` isn't empty on first look -- meant to run
 * after `kopling:demo:seed-fake-data`; does nothing destructive standalone with no Moments yet.
 */
class SeedModeratorsCommand extends Command
{
    protected $signature = 'kopling:moderation:seed-demo';

    protected $description = 'Seed a Moderators group with the moderate permission, and a couple of demo flags';

    public function handle(Manager $manager): int
    {
        $group = Group::query()->firstOrCreate(['name' => 'Moderators']);
        $group->givePermissionTo('kopling-moderation::moderate');

        $this->components->info('Moderators group ready with kopling-moderation::moderate.');

        $target = $manager->moderationTargets()->first(fn ($candidate) => $candidate->model === Moment::class);
        $moments = $target ? Moment::inRandomOrder()->take(2)->get() : collect();

        foreach ($moments as $moment) {
            Flag::updateOrCreate(
                [
                    'flaggable_type' => $target->alias,
                    'flaggable_id' => $moment->id,
                    'person_id' => Person::inRandomOrder()->first()?->id,
                ],
                [
                    'reason' => fake()->randomElement(ModerationReason::cases()),
                    'note' => fake()->boolean(60) ? fake()->sentence() : null,
                    'status' => Flag::STATUS_PENDING,
                ],
            );
        }

        if ($moments->isNotEmpty()) {
            $this->components->info("Seeded {$moments->count()} demo flag(s).");
        }

        return self::SUCCESS;
    }
}
