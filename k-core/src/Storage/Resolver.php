<?php

declare(strict_types=1);

namespace Kopling\Core\Storage;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Kopling\Core\Extension\Manager;

/**
 * Resolves a declared `StorageRequest` id to a real, usable disk. Never silently falls back to a
 * different drive when unmapped/unavailable -- always throws instead -- per the commitment
 * already on record for this contract (`.docs/planning/decisions.md`, 2026-07-10): a quiet
 * fallback would break the app for a fraction of installs on multi-node infra without anyone
 * noticing why.
 */
class Resolver
{
    public function __construct(protected Manager $manager)
    {
    }

    public function resolve(string $requestId): Filesystem
    {
        $request = $this->findRequest($requestId);

        if ($request === null) {
            throw new \RuntimeException("No extension declares a storage request [$requestId].");
        }

        $mapping = StorageMapping::with('drive')->find($requestId);

        if ($mapping === null || $mapping->drive === null || ! $mapping->drive->enabled) {
            throw new \RuntimeException("Storage request [$requestId] is not mapped to an enabled drive.");
        }

        $disk = $this->buildDisk($mapping->drive, $mapping->prefix);

        // A property of what the request itself declared, independent of the drive's own
        // `writable` flag -- a writable drive can still host a read-only-declared purpose.
        if ($request->permission === StoragePermission::ReadOnly) {
            $disk = new ReadOnlyFilesystemAdapter($disk);
        }

        return $disk;
    }

    protected function findRequest(string $requestId): ?StorageRequest
    {
        foreach ($this->manager->storageDrivers() as $requests) {
            foreach ($requests as $request) {
                if ($request->id === $requestId) {
                    return $request;
                }
            }
        }

        return null;
    }

    /**
     * `'prefix'` is Laravel's own generic scoping config key (`FilesystemManager::createFlysystem()`
     * wraps whichever adapter was built in `League\Flysystem\PathPrefixing\PathPrefixedAdapter`
     * when present) -- works uniformly across every driver, unlike hand-rolling a path change
     * into e.g. `local`'s `root` only.
     */
    protected function buildDisk(Drive $drive, ?string $prefix): Filesystem
    {
        $settings = $this->resolveEnvValues($drive->settings ?? []);

        if ($prefix !== null) {
            $settings['prefix'] = $prefix;
        }

        return Storage::build([...$settings, 'driver' => $drive->driver]);
    }

    /**
     * A string value prefixed `env:NAME` resolves via `env()` here, at disk-build time only --
     * never persisted resolved, never rendered back into an admin edit form -- so a secret
     * never has to round-trip through the `drives.settings` column at all. An admin who
     * references an env var that isn't actually set gets a loud failure, not a silent null --
     * same "never silently fall back" rule this class already applies to an unmapped request.
     */
    protected function resolveEnvValues(array $settings): array
    {
        return array_map(function (mixed $value) {
            if (! is_string($value) || ! str_starts_with($value, 'env:')) {
                return $value;
            }

            $name = substr($value, 4);
            $resolved = env($name);

            if ($resolved === null) {
                throw new \RuntimeException("Environment variable [$name], referenced by a drive's settings, is not set.");
            }

            return $resolved;
        }, $settings);
    }
}
