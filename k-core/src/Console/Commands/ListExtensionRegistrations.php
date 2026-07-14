<?php

declare(strict_types=1);

namespace Kopling\Core\Console\Commands;

use Illuminate\Console\Command;
use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\CannotBeDisabled;
use Kopling\Core\Extension\Contract\ChangesTheme;
use Kopling\Core\Extension\Contract\ChangesUx;
use Kopling\Core\Extension\Contract\ExtendsPortals;
use Kopling\Core\Extension\Contract\HasAdminSettings;
use Kopling\Core\Extension\Contract\HasPermissions;
use Kopling\Core\Extension\Contract\HasPortals;
use Kopling\Core\Extension\Contract\RequestsStorageDriver;
use Kopling\Core\Extension\Manager;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * A debugging aid, not a public API -- prints everything one installed extension (or Core
 * itself) actually registers with Kopling, with a runnable-looking usage example for each,
 * so an author (or anyone reviewing what an extension does) doesn't have to cross-reference
 * Manager's collectors and the filesystem by hand.
 */
#[AsCommand(name: 'kopling:extensions:registrations')]
class ListExtensionRegistrations extends Command
{
    protected $signature = 'kopling:extensions:registrations {extension : Composer package name (kopling/example), short name (example), or "core"}';

    protected $description = "List everything an extension registers with Kopling, with a usage example for each";

    public function handle(Manager $manager): int
    {
        $needle = $this->argument('extension');
        $package = $this->resolvePackage($manager, $needle);

        if ($package === null) {
            $this->components->error("No installed extension matches [{$needle}].");

            return self::FAILURE;
        }

        $extension = $manager->extensions()[$package];
        $id = $manager->id($package);

        $this->components->info(sprintf('%s (%s)', $extension::name(), $id));
        $this->line($extension::description());
        $this->newLine();

        $this->components->twoColumnDetail('Implements', $this->contracts($extension) ?: '<fg=gray>none</>');
        $this->components->twoColumnDetail('Cannot be disabled', $extension instanceof CannotBeDisabled ? 'yes' : 'no');
        $this->newLine();

        $this->conventions($manager, $package, $id);
        $this->permissions($manager, $id);
        $this->portals($manager, $id);
        $this->portalExtensions($manager, $package, $id);
        $this->storage($manager, $id);
        $this->ux($manager, $id);
        $this->theme($manager, $id);
        $this->adminSettings($manager, $id);

        return self::SUCCESS;
    }

    protected function resolvePackage(Manager $manager, string $needle): ?string
    {
        foreach (array_keys($manager->extensions()) as $package) {
            if ($needle === $package
                || $needle === $manager->id($package)
                || $needle === basename(str_replace('\\', '/', $package))
            ) {
                return $package;
            }
        }

        return null;
    }

    protected function contracts(AbstractExtension $extension): string
    {
        $known = [
            ChangesUx::class => 'ChangesUx',
            HasPermissions::class => 'HasPermissions',
            HasPortals::class => 'HasPortals',
            ExtendsPortals::class => 'ExtendsPortals',
            RequestsStorageDriver::class => 'RequestsStorageDriver',
            CannotBeDisabled::class => 'CannotBeDisabled',
            ChangesTheme::class => 'ChangesTheme',
            HasAdminSettings::class => 'HasAdminSettings',
        ];

        $implemented = array_intersect_key($known, class_implements($extension));

        return implode(', ', $implemented);
    }

    protected function conventions(Manager $manager, string $package, string $id): void
    {
        $this->components->info('Directory conventions');

        $path = $manager->path($package);
        $conventions = $manager->conventions($package);

        if ($path === null) {
            $this->line('  <fg=gray>Not Composer-discovered -- Core wires its own directories directly in ServiceProvider, not through this mechanism.</>');
            $this->newLine();

            return;
        }

        $this->components->twoColumnDetail('migrations', isset($conventions['migrations'])
            ? implode(', ', array_map('basename', glob($conventions['migrations'].'/*.php') ?: []))
            : 'not present');

        $this->components->twoColumnDetail('views', isset($conventions['views']) ? 'present' : 'not present');
        foreach ($this->viewNames($conventions['views'] ?? null) as $view) {
            $this->line("    <fg=gray>view('{$id}::{$view}')</>");
        }

        $this->components->twoColumnDetail('lang', isset($conventions['lang']) ? 'present' : 'not present');
        foreach ($this->langExamples($conventions['lang'] ?? null, $id) as $example) {
            $this->line("    <fg=gray>{$example}</>");
        }

        $this->components->twoColumnDetail('icon', is_file($path.'/icon/lg.png') ? 'present' : 'not present');

        $this->newLine();
    }

    /**
     * @return array<string>
     */
    protected function viewNames(?string $path): array
    {
        if ($path === null || ! is_dir($path)) {
            return [];
        }

        $names = [];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $relative = substr($file->getPathname(), strlen($path) + 1);
            $names[] = str_replace(['/', '.blade.php'], ['.', ''], $relative);
        }

        sort($names);

        return $names;
    }

    /**
     * @return array<string>
     */
    protected function langExamples(?string $path, string $id): array
    {
        if ($path === null || ! is_dir($path)) {
            return [];
        }

        $examples = [];

        foreach (glob($path.'/*', GLOB_ONLYDIR) ?: [] as $localeDir) {
            foreach (glob($localeDir.'/*.php') ?: [] as $file) {
                $group = pathinfo($file, PATHINFO_FILENAME);

                foreach (array_keys($this->flatten(require $file)) as $key) {
                    $examples[] = "__('{$id}::{$group}.{$key}')";
                }
            }
        }

        return array_values(array_unique($examples));
    }

    /**
     * @return array<string, mixed>
     */
    protected function flatten(array $array, string $prefix = ''): array
    {
        $flat = [];

        foreach ($array as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (is_array($value)) {
                $flat += $this->flatten($value, $path);
            } else {
                $flat[$path] = $value;
            }
        }

        return $flat;
    }

    protected function portalExtensions(Manager $manager, string $package, string $id): void
    {
        $this->components->info('Portal attachments (ExtendsPortals)');

        $extension = $manager->extensions()[$package];

        if (! $extension instanceof ExtendsPortals) {
            $this->line('  <fg=gray>none</>');
            $this->newLine();

            return;
        }

        foreach ($extension->extendsPortals() as $portalExtension) {
            $this->components->twoColumnDetail($portalExtension->portal, implode(', ', array_filter([
                $portalExtension->routes ? 'routes' : null,
                $portalExtension->css ? 'css' : null,
                $portalExtension->js ? 'js' : null,
            ])) ?: 'nothing declared');
        }

        $this->newLine();
    }

    protected function permissions(Manager $manager, string $id): void
    {
        $this->components->info('Permissions (HasPermissions)');

        $permissions = array_filter(
            $manager->permissions(),
            fn ($permission) => str_starts_with($permission->id, $id.'::')
        );

        if ($permissions === []) {
            $this->line('  <fg=gray>none</>');
            $this->newLine();

            return;
        }

        foreach ($permissions as $permission) {
            $this->components->twoColumnDetail($permission->id, $permission->label);
            $this->line("    <fg=gray>@can('{$permission->id}') ... @endcan</>");
        }

        $this->newLine();
    }

    protected function portals(Manager $manager, string $id): void
    {
        $this->components->info('Portals (HasPortals)');

        $portals = $manager->portals()->filter(
            fn ($portal) => str_starts_with($portal->id, $id.'::')
        );

        if ($portals->isEmpty()) {
            $this->line('  <fg=gray>none</>');
            $this->newLine();

            return;
        }

        foreach ($portals as $portal) {
            $this->components->twoColumnDetail($portal->id, "path: /{$portal->path}, layout: {$portal->layout}");
            $this->line("    <fg=gray>route('{$portal->id}')</>");
        }

        $this->newLine();
    }

    protected function storage(Manager $manager, string $id): void
    {
        $this->components->info('Storage requests (RequestsStorageDriver)');

        $requests = $manager->storageDrivers()[$id] ?? [];

        if ($requests === []) {
            $this->line('  <fg=gray>none</>');
            $this->newLine();

            return;
        }

        foreach ($requests as $request) {
            $this->components->twoColumnDetail(
                $request->id,
                "{$request->access->value}, {$request->retention->value}, {$request->permission->value}"
            );
        }

        $this->newLine();
    }

    protected function ux(Manager $manager, string $id): void
    {
        $this->components->info('UI slots (ChangesUx)');

        $entries = $manager->ux()->filter(
            fn ($entry) => str_starts_with($entry->id, $id.'::')
        );

        if ($entries->isEmpty()) {
            $this->line('  <fg=gray>none</>');
            $this->newLine();

            return;
        }

        foreach ($entries as $entry) {
            $condition = match (true) {
                $entry->condition === null => 'always visible',
                $entry->condition instanceof \Closure => 'closure condition',
                default => "requires {$entry->condition}",
            };

            $this->components->twoColumnDetail($entry->id, "{$entry->slot} ({$entry->component}), {$condition}");
        }

        $this->newLine();
    }

    protected function theme(Manager $manager, string $id): void
    {
        $this->components->info('Theme (ChangesTheme)');

        $tokens = $manager->themes()[$id] ?? [];

        if ($tokens === []) {
            $this->line('  <fg=gray>none</>');
            $this->newLine();

            return;
        }

        foreach ($tokens as $token => $value) {
            $this->components->twoColumnDetail($token, $value);
        }

        $this->newLine();
    }

    protected function adminSettings(Manager $manager, string $id): void
    {
        $this->components->info('Admin settings (HasAdminSettings)');

        $fields = $manager->adminSettings()[$id] ?? [];

        if ($fields === []) {
            $this->line('  <fg=gray>none</>');
            $this->newLine();

            return;
        }

        foreach ($fields as $field) {
            $this->components->twoColumnDetail($field->id, "{$field->label} ({$field->component})");
        }

        $this->newLine();
    }
}
