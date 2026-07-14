<?php

declare(strict_types=1);

namespace Kopling\Core\Portal;

/**
 * One extension's contribution to a Portal it doesn't necessarily own -- routes (required into
 * the Portal's own `Route::group()`, so they inherit its prefix/name/middleware for free), and
 * plain hand-written css/js (linked onto the page via the head-assets outlet whenever the
 * current request resolves to this Portal -- see `Manager::extensionAssets()` and
 * `views/layouts/partials/head.blade.php`). `$portal` is the target Portal's fully-qualified id
 * ("kopling-core::community"), written out by the author same as `Ux::after()`/`Ux::before()`
 * reference another extension's fully-qualified id -- `Manager` never prefixes it, since it's a
 * foreign reference, not something this extension owns the naming of.
 */
class PortalExtension
{
    public ?string $routes = null;

    public ?string $css = null;

    public ?string $js = null;

    public function __construct(public readonly string $portal)
    {
    }

    public function routes(string $path): self
    {
        if (! file_exists($path)) {
            throw new \InvalidArgumentException("Routes path does not exist for portal $this->portal: $path");
        }

        $this->routes = $path;

        return $this;
    }

    public function css(string $path): self
    {
        if (! file_exists($path)) {
            throw new \InvalidArgumentException("CSS path does not exist for portal $this->portal: $path");
        }

        $this->css = $path;

        return $this;
    }

    public function js(string $path): self
    {
        if (! file_exists($path)) {
            throw new \InvalidArgumentException("JS path does not exist for portal $this->portal: $path");
        }

        $this->js = $path;

        return $this;
    }

    public function toArray(): array
    {
        return [
            'portal' => $this->portal,
            'routes' => $this->routes,
            'css' => $this->css,
            'js' => $this->js,
        ];
    }

    /**
     * Bypasses `routes()`/`css()`/`js()`'s own `file_exists()` validation -- paths were already
     * validated once, when the cache this reconstructs from was built.
     */
    public static function fromArray(array $data): self
    {
        $instance = new self($data['portal']);
        $instance->routes = $data['routes'];
        $instance->css = $data['css'];
        $instance->js = $data['js'];

        return $instance;
    }
}
