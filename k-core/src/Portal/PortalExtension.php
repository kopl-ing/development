<?php

declare(strict_types=1);

namespace Kopling\Core\Portal;

/**
 * One extension's contribution to a Portal it doesn't necessarily own -- routes (required into
 * the Portal's own `Route::group()`, so they inherit its prefix/name/middleware for free), and
 * two independent asset mechanisms, both linked onto the page via the head-assets outlet
 * whenever the current request resolves to this Portal (see `Manager::extensionAssets()` and
 * `views/layouts/partials/head.blade.php`):
 *
 * - `css()`/`js()`: one plain hand-written, unprocessed file each -- no Tailwind, no bundling.
 * - `compiledAssets()`: this extension's own `resources/css/{name}.css` + `resources/js/
 *   {name}.js` (Tailwind/daisyUI-processed, npm dependencies bundled, same pipeline `k-core`
 *   itself uses -- see CLAUDE.md's "How extensions ship compiled assets"). `{name}` defaults to
 *   this attachment's own `$portal` (sanitized) so an extension attaching to two different
 *   Portals can ship two different bundles without colliding on one `app.js` -- see
 *   `compiledAssets()`'s own docblock. `Manager::viteOrDist()` decides at render time whether to
 *   serve them live via `@vite()` (inside this monorepo, dev server running or built) or the
 *   committed `dist/{name}.css`+`{name}.js` (a standalone install, or before anyone's run the
 *   extension build here) -- one dual-mode implementation, not two.
 *
 * `$portal` is the target Portal's fully-qualified id ("kopling-core::community"), written out
 * by the author same as `Ux::after()`/`Ux::before()` reference another extension's
 * fully-qualified id -- `Manager` never prefixes it, since it's a foreign reference, not
 * something this extension owns the naming of.
 */
class PortalExtension
{
    public ?string $routes = null;

    public ?string $css = null;

    public ?string $js = null;

    public ?string $compiledAssetsRoot = null;

    public ?string $compiledAssetsName = null;

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

    /**
     * `$extensionRoot` is the extension's own package root (`__DIR__.'/..'` from `src/`, same
     * as every other path an extension passes to `routes()`/`css()`/`js()`). `$name` is which
     * `resources/{css,js}/{name}.{css,js}` pair to use -- given explicitly for an extension that
     * wants a specific bundle (e.g. two Portals deliberately sharing one), or left `null` to
     * auto-derive from this attachment's own `$portal` (sanitized: "::"/"/" aren't filename-safe,
     * replaced with "-"), falling back to the plain `app` convention when no portal-specific file
     * exists -- the common case of one extension, one Portal stays zero-ceremony.
     *
     * `resources/js/{name}.js` underneath `$extensionRoot` must exist (proves this extension
     * actually opted into the compiled-asset pipeline), but `dist/{name}.css`+`{name}.js`
     * deliberately aren't validated here: not having been built yet (a fresh monorepo checkout
     * before `npm run build:extensions-dist`) is a normal state, not a misconfiguration --
     * `Manager::viteOrDist()` falls back to `@vite()` in that case instead.
     */
    public function compiledAssets(string $extensionRoot, ?string $name = null): self
    {
        $extensionRoot = rtrim($extensionRoot, '/');
        $name ??= $this->defaultAssetName($extensionRoot);

        if (! file_exists("$extensionRoot/resources/js/$name.js")) {
            throw new \InvalidArgumentException(
                "No resources/js/$name.js found for portal $this->portal: $extensionRoot"
            );
        }

        $this->compiledAssetsRoot = $extensionRoot;
        $this->compiledAssetsName = $name;

        return $this;
    }

    protected function defaultAssetName(string $extensionRoot): string
    {
        $derived = str_replace(['::', '/'], '-', $this->portal);

        return file_exists("$extensionRoot/resources/js/$derived.js") ? $derived : 'app';
    }

    public function toArray(): array
    {
        return [
            'portal' => $this->portal,
            'routes' => $this->routes,
            'css' => $this->css,
            'js' => $this->js,
            'compiledAssetsRoot' => $this->compiledAssetsRoot,
            'compiledAssetsName' => $this->compiledAssetsName,
        ];
    }

    /**
     * Bypasses `routes()`/`css()`/`js()`/`compiledAssets()`'s own validation -- paths were
     * already validated once, when the cache this reconstructs from was built.
     */
    public static function fromArray(array $data): self
    {
        $instance = new self($data['portal']);
        $instance->routes = $data['routes'];
        $instance->css = $data['css'];
        $instance->js = $data['js'];
        $instance->compiledAssetsRoot = $data['compiledAssetsRoot'] ?? null;
        $instance->compiledAssetsName = $data['compiledAssetsName'] ?? null;

        return $instance;
    }
}
