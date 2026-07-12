<?php

declare(strict_types=1);

namespace Kopling\Core\Console\Commands;

use Illuminate\Console\Command;
use Kopling\Core\Extension\Contract\CannotBeDisabled;
use Kopling\Core\Extension\Manager;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Every installed extension and whether it's currently on -- the companion to
 * enable/disable, so a disabled extension (which vanishes from everything that loads one) is
 * still visible somewhere with a name to type at `kopling:extensions:enable`. Reads the raw
 * install list (`discovered()`), not the enabled set, precisely so the off ones show up.
 */
#[AsCommand(name: 'kopling:extensions:list')]
class ListExtensions extends Command
{
    protected $signature = 'kopling:extensions:list';

    protected $description = 'List installed extensions and whether each is enabled';

    public function handle(Manager $manager): int
    {
        $this->components->info('Installed extensions');

        foreach ($manager->discovered() as $package => $extension) {
            $status = match (true) {
                $extension instanceof CannotBeDisabled => '<fg=blue>enabled · locked</>',
                $manager->isEnabled($package) => '<fg=green>enabled</>',
                default => '<fg=yellow>disabled</>',
            };

            $this->components->twoColumnDetail(
                sprintf('%s <fg=gray>(%s)</>', $extension::name(), $manager->id($package)),
                $status,
            );
        }

        return self::SUCCESS;
    }
}
