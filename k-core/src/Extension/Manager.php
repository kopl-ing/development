<?php

declare(strict_types=1);

namespace Kopling\Core\Extension;

use App\Extension as App;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Vite;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Kopling\Core\Core;
use Kopling\Core\Extension\Concerns\AggregatesAdminSettings;
use Kopling\Core\Extension\Concerns\AggregatesCommands;
use Kopling\Core\Extension\Concerns\AggregatesEditorNodes;
use Kopling\Core\Extension\Concerns\AggregatesIcons;
use Kopling\Core\Extension\Concerns\AggregatesModels;
use Kopling\Core\Extension\Concerns\AggregatesModelValidation;
use Kopling\Core\Extension\Concerns\AggregatesPermissions;
use Kopling\Core\Extension\Concerns\AggregatesPortalExtensions;
use Kopling\Core\Extension\Concerns\AggregatesPortals;
use Kopling\Core\Extension\Concerns\AggregatesStorageDrivers;
use Kopling\Core\Extension\Concerns\AggregatesThemes;
use Kopling\Core\Extension\Concerns\AggregatesUx;
use Kopling\Core\Extension\Concerns\DispatchesEventListeners;
use Kopling\Core\Extension\Contract\CannotBeDisabled;
use Kopling\Core\Extension\LoadOrder\Resolver;
use Kopling\Core\Settings\EnabledExtensions;

use function once;

/**
 * Aggregates every installed extension's declarations (permissions, ux entries, models, icons,
 * themes, portals, settings, ...) from a `RegistrationCache` when warm, otherwise by looping
 * `extensions()` and filtering by the matching contract. Local ids get prefixed with the
 * declaring extension's own `id()` so two extensions can't collide on the same name.
 *
 * The per-contract aggregation methods themselves live in `Concerns/` (one trait per `Contract`),
 * this class keeps only extension discovery and generic asset-serving that isn't gated by a
 * single contract.
 */
class Manager
{
    use AggregatesAdminSettings;
    use AggregatesCommands;
    use AggregatesEditorNodes;
    use AggregatesIcons;
    use AggregatesModels;
    use AggregatesModelValidation;
    use AggregatesPermissions;
    use AggregatesPortalExtensions;
    use AggregatesPortals;
    use AggregatesStorageDrivers;
    use AggregatesThemes;
    use AggregatesUx;
    use DispatchesEventListeners;

    /**
     * `k-core`'s own compiled bundle names -- `head.blade.php` loops these to know which `name`s
     * to call `viteOrDist()` with.
     *
     * @var array<int, string>
     */
    public const CORE_COMPILED_BUNDLES = ['app', 'editor', 'emoji-picker', 'tag-input', 'icon-picker'];

    public function __construct(
        protected Manifest $manifest,
        protected Dispatcher $events,
        protected RegistrationCache $cache,
    )
    {
    }

    /**
     * `Core` is always first and always present, even though it isn't Composer-discovered.
     * `$includeDisabled = true` skips the `EnabledExtensions` filter (`CannotBeDisabled`
     * implementors are always kept either way) -- used by the admin extensions list.
     *
     * @return array<string, AbstractExtension>
     */
    public function extensions(bool $includeDisabled = false): array
    {
        return once(function () use ($includeDisabled) {
            $discovered = ['kopling/core' => new Core()];

            // A consuming app's own `App\Extension` -- opt-in, not Composer-discovered (there's
            // no package for it), toggleable/orderable like any other extension once found.
            if (class_exists(App::class) && is_subclass_of(App::class, AbstractExtension::class)) {
                $discovered['app'] = new App();
            }

            foreach ($this->manifest->extensions() as $package => $extension) {
                $class = $extension['namespace'].'Extension';

                if (! class_exists($class) || ! is_subclass_of($class, AbstractExtension::class)) {
                    continue;
                }

                $discovered[$package] = new $class();
            }

            $resolved = Resolver::resolve($discovered);

            if ($includeDisabled) {
                return $resolved;
            }

            return array_filter(
                $resolved,
                fn (AbstractExtension $extension, string $package) => $extension instanceof CannotBeDisabled
                    || EnabledExtensions::isEnabled($this->id($package)),
                ARRAY_FILTER_USE_BOTH,
            );
        });
    }

    /**
     * Directory-convention paths (migrations/views/lang) an extension gets just by the directory
     * existing. Routes/css/js aren't included -- those need a target Portal, see `ExtendsPortals`.
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

        foreach (['migrations', 'views', 'lang'] as $kind) {
            $find = realpath($path.'/'.$kind);

            if ($find && is_dir($path.'/'.$kind)) {
                $conventions[$kind] = $find;
            }
        }

        return $conventions;
    }

    public function path(string $package): ?string
    {
        return match ($package) {
            'kopling/core' => dirname(__DIR__, 2),
            'app' => base_path(),
            default => $this->manifest->extensions()[$package]['path'] ?? null,
        };
    }

    /**
     * The namespace an extension's views/translations register under -- includes the vendor so
     * two different vendors' same-named packages ("kopling/example", "acme/example") don't collide.
     */
    public function id(string $package): string
    {
        return str_replace('/', '-', $package);
    }

    /**
     * Resolves a user-typed reference -- package name, `id()` form, or short name -- back to the
     * real Composer package key. Null when nothing installed matches.
     */
    public function resolvePackage(string $needle): ?string
    {
        foreach (array_keys($this->extensions(includeDisabled: true)) as $package) {
            if ($needle === $package
                || $needle === $this->id($package)
                || $needle === basename(str_replace('\\', '/', $package))
            ) {
                return $package;
            }
        }

        return null;
    }

    /**
     * Keyed by a hash of its own already-validated path, not anything request-derived --
     * `ExtensionAssetController` looks a request's `key` up here rather than accepting a raw
     * path, so a request can never walk this into an arbitrary filesystem read. Only single,
     * standalone files -- a compiled dist bundle's own directory (which Vite may have littered
     * with sibling chunks, e.g. a shared `preload-helper.js`, that a bundle's relative `import`
     * needs to reach too) is served through `compiledAssetDirectories()` instead, since a single
     * file-to-hash registry can't answer a request for a chunk it never explicitly enumerated.
     *
     * @return Collection<string, array{path: string, mime: string}>
     */
    public function extensionAssets(): Collection
    {
        $assets = [];

        foreach ($this->portalExtensions() as $group) {
            foreach ($group as $portalExtension) {
                foreach (['css' => 'text/css', 'js' => 'application/javascript'] as $kind => $mime) {
                    $path = $portalExtension->{$kind};

                    if ($path === null) {
                        continue;
                    }

                    $assets[static::assetKey($path)] = ['path' => $path, 'mime' => $mime];
                }
            }
        }

        foreach (array_keys($this->extensions(includeDisabled: true)) as $package) {
            $root = $this->path($package);

            if ($root === null) {
                continue;
            }

            foreach (['lg', 'sm'] as $size) {
                $path = $root.'/icon/'.$size.'.png';

                if (! is_file($path)) {
                    continue;
                }

                $assets[static::assetKey($path)] = ['path' => $path, 'mime' => 'image/png'];
            }
        }

        return collect($assets);
    }

    public static function assetUrl(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        return route('kopling-core::assets', ['key' => static::assetKey($path)]);
    }

    /**
     * Every known compiled-dist directory (k-core's own, plus every distinct
     * `PortalExtension::compiledAssetsRoot`), keyed by a hash of the directory path itself --
     * `DistAssetController` looks a request's `key` up here, then confines `{filename}` inside
     * that one directory (see its own docblock), rather than trusting either segment on its own.
     *
     * @return Collection<string, string>
     */
    public function compiledAssetDirectories(): Collection
    {
        $dirs = [rtrim($this->path('kopling/core'), '/').'/dist'];

        foreach ($this->portalExtensions() as $group) {
            foreach ($group as $portalExtension) {
                if ($portalExtension->compiledAssetsRoot !== null) {
                    $dirs[] = $portalExtension->compiledAssetsRoot.'/dist';
                }
            }
        }

        return collect($dirs)
            ->unique()
            ->filter(fn (string $dir) => is_dir($dir))
            ->mapWithKeys(fn (string $dir) => [static::assetKey($dir) => $dir]);
    }

    public static function distAssetUrl(string $dir, string $filename): string
    {
        return route('kopling-core::dist-assets', [
            'key' => static::assetKey(rtrim($dir, '/')),
            'filename' => $filename,
        ]);
    }

    /**
     * `<link>`/`<script>` tags for one extension's compiled `resources/css/{name}.css` +
     * `resources/js/{name}.js` -- `@vite()` when the monorepo's own dev build covers this exact
     * source path (dev server running, or `npm run build` already produced a manifest entry
     * for it -- the same live experience `k-core`'s own assets already have), falling back to
     * the committed `dist/{name}.css`+`{name}.js` otherwise (a standalone Composer install, or
     * before anyone's run `npm run build:extensions-dist` in this monorepo at all). `$name`
     * matches whatever `PortalExtension::compiledAssets()` resolved (`compiledAssetsName`) --
     * used from `views/layouts/partials/head.blade.php`.
     */
    public static function viteOrDist(string $extensionRoot, string $name = 'app'): string
    {
        $extensionRoot = rtrim($extensionRoot, '/');
        $cssSource = "$extensionRoot/resources/css/$name.css";
        $jsSource = "$extensionRoot/resources/js/$name.js";
        $relativeCss = ltrim(Str::after($cssSource, base_path()), '/');
        $relativeJs = ltrim(Str::after($jsSource, base_path()), '/');

        $vite = app(Vite::class);

        // Checked against whichever of css/js actually has a source file, not always css --
        // an asymmetric bundle (js-only, like a Portal-specific entry with no matching css)
        // would otherwise never match here even when the dev build genuinely covers it, since
        // a manifest key can only exist for an input that was actually declared.
        $coveredByManifest = (is_file($cssSource) && static::viteManifestHas($relativeCss))
            || (is_file($jsSource) && static::viteManifestHas($relativeJs));

        // `isRunningHot()` alone only tells us *a* dev server is up somewhere -- not that it's
        // the one covering this particular `$extensionRoot`. Own assets means `realpath()`
        // lands outside `vendor/`: true for the app's own base path, and for this monorepo's
        // own `k-core`/`k-extensions` -- whether reached directly (a real, non-symlinked
        // checkout resolves straight to itself, outside `vendor/`) or through their
        // `vendor/kopling/*` path-repo symlinks (`realpath()` resolves the symlink away,
        // landing outside `vendor/` all the same) -- one shared dev server covers all of it.
        // False for a standalone Composer install, where `vendor/kopling/core` is a real
        // checkout with its own separate, unrelated npm project -- there, `realpath()` stays
        // inside `vendor/` since there's no symlink to resolve away, so this must always fall
        // through to its committed `dist/` instead.
        $vendorPath = realpath(base_path('vendor'));
        $realExtensionRoot = realpath($extensionRoot);
        $isOwnAssets = $realExtensionRoot !== false
            && ($vendorPath === false || ! Str::startsWith($realExtensionRoot, $vendorPath));

        if (($vite->isRunningHot() && $isOwnAssets) || $coveredByManifest) {
            $entries = array_values(array_filter([
                is_file($cssSource) ? $relativeCss : null,
                is_file($jsSource) ? $relativeJs : null,
            ]));

            return $vite($entries)->toHtml();
        }

        $html = '';
        $distDir = "$extensionRoot/dist";
        $distCss = "$distDir/$name.css";
        $distJs = "$distDir/$name.js";

        // Through distAssetUrl(), not assetUrl() -- a dist bundle may `import` a sibling chunk
        // Vite emitted alongside it (e.g. a shared preload-helper.js), which the browser
        // resolves relative to this very URL, so entry and chunk must share one directory-scoped
        // route rather than each being an unrelated single-file hash.
        if (is_file($distCss)) {
            $html .= '<link rel="stylesheet" href="'.e(static::distAssetUrl($distDir, "$name.css")).'">';
        }

        if (is_file($distJs)) {
            $html .= '<script type="module" src="'.e(static::distAssetUrl($distDir, "$name.js")).'"></script>';
        }

        return $html;
    }

    /**
     * Reads `public/build/manifest.json` directly rather than going through `Vite::asset()`
     * (which throws when an entry is missing, and has no public "does this key exist" check)
     * -- memoized per-request, so checking every extension's compiled assets on one page never
     * re-reads/re-decodes the same file more than once.
     */
    protected static function viteManifestHas(string $key): bool
    {
        static $manifest = null;

        if ($manifest === null) {
            $path = public_path('build/manifest.json');
            $manifest = is_file($path) ? (json_decode(file_get_contents($path), true) ?? []) : [];
        }

        return array_key_exists($key, $manifest);
    }

    public function iconUrl(string $package, string $size = 'lg'): ?string
    {
        $root = $this->path($package);

        if ($root === null) {
            return null;
        }

        $path = $root.'/icon/'.$size.'.png';

        return is_file($path) ? static::assetUrl($path) : null;
    }

    protected static function assetKey(string $path): string
    {
        return hash('xxh3', $path);
    }
}
