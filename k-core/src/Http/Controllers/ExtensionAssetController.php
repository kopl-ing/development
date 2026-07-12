<?php

declare(strict_types=1);

namespace Kopling\Core\Http\Controllers;

use Kopling\Core\Extension\Manager;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves the css/js files declared through `Extension\Contract\ExtendsPortals`/`PortalExtension`
 * -- files that live inside a package directory (`vendor/...` or `k-extensions/...`), not under
 * `public/`, so nothing else makes them web-reachable. `$key` is never treated as a filesystem
 * path: it's looked up against `Manager::extensionAssets()`, a registry built entirely from
 * paths each `PortalExtension::css()/js()` call already validated with `file_exists()` at
 * registration time. A request can therefore only ever resolve to one of those specific,
 * known-safe paths -- there is no `{package}/{path}`-shaped parameter here to walk with `../`.
 */
class ExtensionAssetController
{
    public function __invoke(Manager $manager, string $key): BinaryFileResponse
    {
        $asset = $manager->extensionAssets()->get($key);

        abort_if($asset === null, 404);

        return response()->file($asset['path'], [
            'Content-Type' => $asset['mime'],
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
