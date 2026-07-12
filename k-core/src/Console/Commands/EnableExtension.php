<?php

declare(strict_types=1);

namespace Kopling\Core\Console\Commands;

use Illuminate\Console\Command;
use Kopling\Core\Extension\Manager;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Turns a previously-disabled extension back on (see DisableExtension). A no-op on one that's
 * already enabled, an error on a package that isn't installed at all -- enabling only ever
 * clears the disabled mark, it can't conjure an extension that was never `composer require`d.
 */
#[AsCommand(name: 'kopling:extensions:enable')]
class EnableExtension extends Command
{
    protected $signature = 'kopling:extensions:enable {extension : Composer package name (kopling/example), id (kopling-example), or short name (example)}';

    protected $description = 'Re-enable a disabled extension';

    public function handle(Manager $manager): int
    {
        $needle = $this->argument('extension');
        $package = $manager->resolvePackage($needle);

        if ($package === null) {
            $this->components->error("No installed extension matches [{$needle}].");

            return self::FAILURE;
        }

        if ($manager->isEnabled($package)) {
            $this->components->info("[{$package}] is already enabled.");

            return self::SUCCESS;
        }

        $manager->enable($package);

        $this->components->info("Enabled [{$package}].");

        return self::SUCCESS;
    }
}
