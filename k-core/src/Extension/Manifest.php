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

        $this->write((new Collection($packages))
            ->filter(fn ($package) => ($package['type'] ?? null) === 'kopling-extension')
            ->mapWithKeys(function ($package) {
                $namespace = array_key_first($package['autoload']['psr-4'] ?? []);
                $path = realpath($this->vendorPath.'/'.$package['name']) ?: null;

                return [$package['name'] => [
                    'namespace' => $namespace,
                    'path' => $path,
                ]];
            })
            ->filter(fn ($extension) => $extension['namespace'] && $extension['path'])
            ->all());
    }

    /**
     * @return array<string, array{namespace: string, path: string}>
     */
    public function extensions(): array
    {
        return $this->getManifest();
    }
}
