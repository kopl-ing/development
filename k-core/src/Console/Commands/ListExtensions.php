<?php

declare(strict_types=1);

namespace Kopling\Core\Console\Commands;

use Illuminate\Console\Command;
use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\CannotBeDisabled;
use Kopling\Core\Extension\Manager;
use Kopling\Core\Settings\EnabledExtensions;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * A quick overview of every installed extension -- `kopling:extensions:registrations` is the
 * deep-dive for one extension at a time; this is the shallow, at-a-glance list across all of
 * them, package + name + description + whether it's actually enabled right now.
 */
#[AsCommand(name: 'kopling:extensions:list')]
class ListExtensions extends Command
{
    protected $signature = 'kopling:extensions:list';

    protected $description = 'List every installed extension with its name, description, and enabled state';

    public function handle(Manager $manager): int
    {
        $extensions = $manager->extensions(includeDisabled: true);

        $rows = collect($extensions)->map(function (AbstractExtension $extension, string $package) use ($manager) {
            $id = $manager->id($package);
            $cannotBeDisabled = $extension instanceof CannotBeDisabled;

            return [
                $package,
                $extension::name(),
                $extension::description(),
                $cannotBeDisabled || EnabledExtensions::isEnabled($id) ? 'yes' : 'no',
            ];
        })->values()->all();

        $this->table(['Package', 'Name', 'Description', 'Enabled'], $rows);

        return self::SUCCESS;
    }
}
