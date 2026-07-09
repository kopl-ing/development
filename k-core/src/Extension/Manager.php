<?php

declare(strict_types=1);

namespace Kopling\Core\Extension;

use Kopling\Core\Extension\Contract\RequestsStorageDriver;
use Kopling\Core\Storage\StorageRequest;

class Manager
{
    /**
     * @var array<string, AbstractExtension>|null
     */
    protected ?array $extensions = null;

    public function __construct(protected Manifest $manifest)
    {
    }

    /**
     * Every discovered extension, keyed by Composer package name, instantiated once.
     *
     * @return array<string, AbstractExtension>
     */
    public function extensions(): array
    {
        if ($this->extensions !== null) {
            return $this->extensions;
        }

        $this->extensions = [];

        foreach ($this->manifest->extensions() as $package => $extension) {
            $class = $extension['namespace'].'Extension';

            if (! class_exists($class) || ! is_subclass_of($class, AbstractExtension::class)) {
                continue;
            }

            $this->extensions[$package] = new $class();
        }

        return $this->extensions;
    }

    /**
     * Directory-convention paths this package declares, keyed by kind. An extension gets
     * these registered (migrations, views, routes, lang) or made available (css, js) purely
     * by the directory existing -- no contract to implement, no code to write.
     *
     * @return array<string, string>
     */
    public function conventions(string $package): array
    {
        $path = $this->path($package);

        if ($path === null) {
            return [];
        }

        $conventions = [];

        foreach (['migrations', 'views', 'css', 'js', 'routes', 'lang'] as $kind) {
            if (is_dir($path.'/'.$kind)) {
                $conventions[$kind] = $path.'/'.$kind;
            }
        }

        return $conventions;
    }

    public function path(string $package): ?string
    {
        return $this->manifest->extensions()[$package]['path'] ?? null;
    }

    /**
     * The namespace an extension's views/translations/etc. get registered under. Includes
     * the vendor, not just the package name -- two different vendors can both publish an
     * extension called "example", and dropping the vendor would make their view/translation
     * namespaces collide (kopling/example and acme/example would both register "example::").
     */
    public function id(string $package): string
    {
        return str_replace('/', '-', $package);
    }

    /**
     * @return array<StorageRequest>
     */
    public function storageDrivers(): array
    {
        $requests = [];

        foreach ($this->extensions() as $extension) {
            if ($extension instanceof RequestsStorageDriver) {
                array_push($requests, ...$extension->storage());
            }
        }

        return $requests;
    }
}
