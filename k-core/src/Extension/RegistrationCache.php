<?php

declare(strict_types=1);

namespace Kopling\Core\Extension;

/**
 * A flatfile cache for `Manager`'s own extension-derived aggregations (permissions/portals/
 * portalExtensions/storageDrivers/ux/themes/adminSettings/commands) -- a separate concern and a
 * separate file from `Manifest`'s `kopling-extensions.php`: that one is Composer package
 * discovery (input to instantiating extensions at all), this one is a downstream computation
 * (running every installed extension's own contract methods and resolving Add/Replace/Remove),
 * only ever valid for a specific extension set *and* source, not just "which packages are
 * installed."
 *
 * Deliberately not wired to any automatic rebuild trigger (unlike `Manifest`'s
 * `post-autoload-dump` composer hook): editing an extension's `ux()`/`permissions()` method is a
 * source-code change, not a Composer operation, so an automatic hook would never re-fire for it
 * and the cache would silently go stale during ordinary local development. Only ever written by
 * explicitly running `kopling:extensions:cache` (`Console\Commands\CacheRegistrations`) -- until
 * that command runs at least once, `get()` always returns `null` and `Manager` computes live,
 * exactly as it always has. Meant to eventually be rebuilt automatically when extension enable/
 * disable exists (not built yet); for now it's rebuilt by hand, the same way
 * `kopling-extensions.php` is discovered.
 */
class RegistrationCache
{
    /**
     * @var array<string, mixed>|null
     */
    protected ?array $cache = null;

    public function __construct(protected string $path)
    {
    }

    public function has(): bool
    {
        return file_exists($this->path);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(): ?array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        if (! $this->has()) {
            return null;
        }

        return $this->cache = require $this->path;
    }

    /**
     * @param  array<string, mixed>  $registrations
     */
    public function write(array $registrations): void
    {
        $directory = dirname($this->path);

        if (! is_dir($directory)) {
            mkdir($directory, recursive: true);
        }

        file_put_contents($this->path, '<?php return '.var_export($registrations, true).';');

        $this->cache = $registrations;
    }

    public function clear(): void
    {
        if ($this->has()) {
            unlink($this->path);
        }

        $this->cache = null;
    }
}
