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
 * `EnabledExtensions`/`RegistrationCache` mechanism, just reachable without a browser.
 *
 * Refuses a `CannotBeDisabled` extension explicitly, same guard
 * `Admin\Controllers\SettingsController::toggle()` already applies server-side, never trusting a
 * UI having hidden the button. Belt-and-braces: `Manager::extensions()`'s own filter
 * (`$extension instanceof CannotBeDisabled || EnabledExtensions::isEnabled(...)`) already makes
 * it structurally impossible for a CannotBeDisabled extension to actually disappear from the
 * enabled set even if this guard were skipped -- but silently writing a no-op id into the
 * disabled list and claiming success would still be dishonest feedback to whoever ran this.
 */
#[AsCommand(name: 'kopling:extensions:disable')]
class DisableExtension extends Command
{
    protected $signature = 'kopling:extensions:disable {package : Composer package name (kopling/example), short name (example), or "core"}';

    protected $description = 'Disable an installed extension';

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
            $this->components->error("{$extension::name()} ({$id}) can't be disabled.");

            return self::FAILURE;
        }

        if (! EnabledExtensions::isEnabled($id)) {
            $this->components->info("{$extension::name()} ({$id}) is already disabled.");

            return self::SUCCESS;
        }

        $allIds = array_map(fn (string $p) => $manager->id($p), array_keys($manager->extensions(includeDisabled: true)));

        EnabledExtensions::disable($id, $allIds);
        $cache->clear();

        $this->components->info("Disabled {$extension::name()} ({$id}).");

        return self::SUCCESS;
    }
}
