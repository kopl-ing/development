<?php

declare(strict_types=1);

namespace Kopling\Core\Extension;

/**
 * A flatfile cache for `Manager`'s own extension-derived aggregations. Only written by explicitly
 * running `kopling:extensions:cache` -- until then `get()` always returns `null` and `Manager`
 * computes live. Not wired to any automatic rebuild trigger: editing `ux()`/`permissions()` is a
 * source change, not a Composer operation, so an automatic hook would never re-fire for it.
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
