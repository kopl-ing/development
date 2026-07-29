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
 * - `compiledAssets()`: this extension's own `resources/css/app.css` + `resources/js/app.js`
 *   (Tailwind/daisyUI-processed, npm dependencies bundled, same pipeline `k-core` itself uses --
 *   see CLAUDE.md's "How k-core ships compiled assets"). `Manager::viteOrDist()` decides at
 *   render time whether to serve them live via `@vite()` (inside this monorepo, dev server
 *   running or built) or the committed `dist/app.css`+`app.js` (a standalone install, or before
 *   anyone's run the extension build here) -- one dual-mode implementation, not two.
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
     * as every other path an extension passes to `routes()`/`css()`/`js()`) -- `resources/css/
     * app.css` + `resources/js/app.js` underneath it must exist (proves this extension actually
     * opted into the compiled-asset pipeline), but `dist/app.css`+`app.js` deliberately aren't
     * validated here: not having been built yet (a fresh monorepo checkout before `npm run
     * build:extensions-dist`) is a normal state, not a misconfiguration -- `Manager::
     * viteOrDist()` falls back to `@vite()` in that case instead.
     */
    public function compiledAssets(string $extensionRoot): self
    {
        $extensionRoot = rtrim($extensionRoot, '/');

        if (! file_exists($extensionRoot.'/resources/js/app.js')) {
            throw new \InvalidArgumentException(
                "No resources/js/app.js found for portal $this->portal: $extensionRoot"
            );
        }

        $this->compiledAssetsRoot = $extensionRoot;

        return $this;
    }

    public function toArray(): array
    {
        return [
            'portal' => $this->portal,
            'routes' => $this->routes,
            'css' => $this->css,
            'js' => $this->js,
            'compiledAssetsRoot' => $this->compiledAssetsRoot,
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

        return $instance;
    }
}
