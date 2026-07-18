<?php

declare(strict_types=1);

namespace Kopling\Core\Console\Commands;

use Illuminate\Console\Command;
use Kopling\Core\Extension\Manager;
use Kopling\Core\Extension\RegistrationCache;
use Kopling\Core\Ux\Editor\EditorNode;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Rebuilds `RegistrationCache`'s flatfile from a live-computed snapshot of `Manager`'s own
 * extension-derived aggregations. Unlike `DiscoverExtensions`, not wired to any automatic
 * trigger yet -- see `RegistrationCache`'s own docblock for why. Clears the cache first so every
 * aggregation below computes live, not from whatever was cached before.
 */
#[AsCommand(name: 'kopling:extensions:cache')]
class CacheRegistrations extends Command
{
    protected $signature = 'kopling:extensions:cache';

    protected $description = "Cache Manager's extension-derived registrations (permissions, portals, ux, ...) to a flatfile";

    public function handle(Manager $manager, RegistrationCache $cache): void
    {
        $cache->clear();

        $cache->write([
            'permissions' => array_map(fn ($permission) => $permission->toArray(), $manager->permissions()),
            'portals' => $manager->portals()->map(fn ($portal) => $portal->toArray())->values()->all(),
            'portalExtensions' => $manager->portalExtensions()
                ->map(fn ($group) => $group->map(fn ($portalExtension) => $portalExtension->toArray())->all())
                ->all(),
            'storageDrivers' => collect($manager->storageDrivers())
                ->map(fn (array $requests) => array_map(fn ($request) => $request->toArray(), $requests))
                ->all(),
            'ux' => $manager->ux()->map(fn ($entry) => $entry->toArray())->all(),
            'themes' => $manager->themes()->all(),
            'themeColorSchemes' => $manager->themeColorSchemes()->map(fn ($scheme) => $scheme->value)->all(),
            'icons' => $manager->icons()->map(fn ($icon) => $icon->toArray())->all(),
            'editorNodes' => array_map(fn (EditorNode $node) => $node->value, $manager->editorNodes()),
            'iconPackMappings' => $manager->iconPackMappings()->all(),
            'adminSettings' => $manager->adminSettings()
                ->map(fn (array $fields) => array_map(fn ($field) => $field->toArray(), $fields))
                ->all(),
            'commands' => $manager->commands(),
            'modelValidations' => $manager->modelValidationRules(),
        ]);

        $this->components->info('Cached Kopling extension registrations.');
    }
}
