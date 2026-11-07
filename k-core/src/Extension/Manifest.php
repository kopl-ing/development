<?php

declare(strict_types=1);

namespace Kopling\Core\Extension;

use Illuminate\Foundation\PackageManifest;
use Illuminate\Support\Collection;

/**
 * A Kopling-specific counterpart to Laravel's own PackageManifest: same underlying
 * mechanics (read vendor/composer/installed.json once, write a compiled cache file), but
 * keyed on Composer's "type": "kopling-extension" instead of "extra.laravel" -- Laravel's
 * own manifest silently drops any package without a non-empty "extra.laravel" value (its
 * build() ends in a bare ->filter(), and [] is falsy), so it can't be reused as-is to find
 * packages that declare no providers/aliases of their own.
 */
class Manifest extends PackageManifest
{
    public function build()
    {
        $packages = [];

        if ($this->files->exists($path = $this->vendorPath.'/composer/installed.json')) {
            $installed = json_decode($this->files->get($path), true);

            $packages = $installed['packages'] ?? $installed;
        }

        $installed = (new Collection($packages))
            ->filter(fn ($package) => ($package['type'] ?? null) === 'kopling-extension')
            ->mapWithKeys(function ($package) {
                $namespace = array_key_first($package['autoload']['psr-4'] ?? []);
                $path = realpath($this->vendorPath.'/'.$package['name']) ?: null;

                return [$package['name'] => [
                    'namespace' => $namespace,
                    'path' => $path,
                ]];
            })
            ->all();

        $this->write((new Collection(array_merge($installed, $this->localExtensions())))
            ->filter(fn ($extension) => $extension['namespace'] && $extension['path'])
            ->all());
    }

    /**
     * Extensions that live inside the app itself instead of `vendor/`, declared in its own
     * composer.json as `"extra": {"kopling": {"extensions": {"vendor/name": "relative/dir"}}}`;
     * their classes autoload through the app's own PSR-4 map, so Composer never lists them in
     * installed.json. The package name is the key (and so the extension id), and a local
     * extension replaces an installed package of the same name.
     *
     * @return array<string, array{namespace: ?string, path: ?string}>
     */
    protected function localExtensions(): array
    {
        if (! $this->files->exists($path = $this->basePath.'/composer.json')) {
            return [];
        }

        $root = json_decode($this->files->get($path), true) ?? [];
        $declared = $root['extra']['kopling']['extensions'] ?? [];
        $extensions = [];

        foreach ($declared as $package => $directory) {
            $path = realpath($this->basePath.'/'.$directory) ?: null;

            $extensions[$package] = [
                'namespace' => $path ? $this->localNamespace($path, $root['autoload']['psr-4'] ?? []) : null,
                'path' => $path,
            ];
        }

        return $extensions;
    }

    /**
     * @param  array<string, string|array<int, string>>  $rootPsr4
     */
    protected function localNamespace(string $path, array $rootPsr4): ?string
    {
        foreach ($rootPsr4 as $namespace => $directories) {
            foreach ((array) $directories as $directory) {
                $directory = realpath($this->basePath.'/'.$directory);

                if ($directory && ($directory === $path || str_starts_with($directory, $path.DIRECTORY_SEPARATOR))) {
                    return $namespace;
                }
            }
        }

        if ($this->files->exists($own = $path.'/composer.json')) {
            return array_key_first(json_decode($this->files->get($own), true)['autoload']['psr-4'] ?? []);
        }

        return null;
    }

    /**
     * @return array<string, array{namespace: string, path: string}>
     */
    public function extensions(): array
    {
        return $this->getManifest();
    }
}
