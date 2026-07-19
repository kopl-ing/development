<?php

declare(strict_types=1);

namespace Kopling\Core\Console\Commands;

use Illuminate\Console\Command;
use Kopling\Core\Extension\Contract\CannotBeDisabled;
use Kopling\Core\Extension\Manager;
use Kopling\Core\Extension\RegistrationCache;
use Kopling\Core\Settings\EnabledExtensions;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * The CLI counterpart to `kopling-admin::admin/settings`'s own toggle button -- same
 * `EnabledExtensions`/`RegistrationCache` mechanism, just reachable without a browser (a fresh
 * install's provisioning script, CI, a host without the admin extension installed at all).
 */
#[AsCommand(name: 'kopling:extensions:enable')]
class EnableExtension extends Command
{
    protected $signature = 'kopling:extensions:enable {package : Composer package name (kopling/example), short name (example), or "core"}';

    protected $description = 'Enable an installed extension';

    public function handle(Manager $manager, RegistrationCache $cache): int
    {
        $needle = $this->argument('package');
        $package = $manager->resolvePackage($needle);

        if ($package === null) {
            $this->components->error("No installed extension matches [{$needle}].");

            return self::FAILURE;
        }

        $extension = $manager->extensions(includeDisabled: true)[$package];
        $id = $manager->id($package);

        if ($extension instanceof CannotBeDisabled) {
            $this->components->info("{$extension::name()} ({$id}) can't be disabled -- it's always enabled.");

            return self::SUCCESS;
        }

        if (EnabledExtensions::isEnabled($id)) {
            $this->components->info("{$extension::name()} ({$id}) is already enabled.");

            return self::SUCCESS;
        }

        $allIds = array_map(fn (string $p) => $manager->id($p), array_keys($manager->extensions(includeDisabled: true)));

        EnabledExtensions::enable($id, $allIds);
        $cache->clear();

        $this->components->info("Enabled {$extension::name()} ({$id}).");

        return self::SUCCESS;
    }
}
