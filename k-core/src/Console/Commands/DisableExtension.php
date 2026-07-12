<?php

declare(strict_types=1);

namespace Kopling\Core\Console\Commands;

use Illuminate\Console\Command;
use Kopling\Core\Extension\Manager;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Turns an installed extension off without uninstalling it -- it stays `composer require`d,
 * its tables and data stay put, it just stops loading (no routes, views, permissions, ux, or
 * theme) until re-enabled. The CLI half of the enable/disable toggle; an admin screen is a
 * later, separate concern (issue #6). Core, and anything else marking itself
 * `CannotBeDisabled`, is refused.
 */
#[AsCommand(name: 'kopling:extensions:disable')]
class DisableExtension extends Command
{
    protected $signature = 'kopling:extensions:disable {extension : Composer package name (kopling/example), id (kopling-example), or short name (example)}';

    protected $description = 'Disable an installed extension without uninstalling it';

    public function handle(Manager $manager): int
    {
        $needle = $this->argument('extension');
        $package = $manager->resolvePackage($needle);

        if ($package === null) {
            $this->components->error("No installed extension matches [{$needle}].");

            return self::FAILURE;
        }

        if (! $manager->isEnabled($package)) {
            $this->components->info("[{$package}] is already disabled.");

            return self::SUCCESS;
        }

        try {
            $manager->disable($package);
        } catch (\InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Disabled [{$package}].");

        return self::SUCCESS;
    }
}
