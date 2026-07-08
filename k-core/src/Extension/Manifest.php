<?php

declare(strict_types=1);

namespace Kopling\Core\Extension;

use Illuminate\Foundation\PackageManifest;
use Illuminate\Support\Collection;

class Manifest extends PackageManifest
{
    public function build()
    {
        $extensions = [];

        if ($this->files->exists($path = $this->vendorPath.'/composer/installed.json')) {
            $installed = json_decode($this->files->get($path), true);

            $extensions = $installed['packages'] ?? $installed;
        }

        $ignoreAll = in_array('*', $ignore = $this->packagesToIgnore());

        $this->write((new Collection($extensions))->mapWithKeys(function ($package) {
            return [$this->format($package['name']) => $package['extra']['kopling'] ?? []];
        })->each(function ($configuration) use (&$ignore) {
            $ignore = array_merge($ignore, $configuration['dont-discover'] ?? []);
        })->reject(function ($configuration, $package) use ($ignore, $ignoreAll) {
            return $ignoreAll || in_array($package, $ignore);
        })->filter()->all());
    }
}
