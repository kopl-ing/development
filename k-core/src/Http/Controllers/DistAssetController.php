<?php

declare(strict_types=1);

namespace Kopling\Core\Http\Controllers;

use Kopling\Core\Extension\Manager;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves one compiled dist directory's files (see `Manager::compiledAssetDirectories()`) --
 * `{key}` is a hash of that one pre-registered, already-`is_dir()`-checked directory, never a
 * raw path. `{filename}` is real, request-derived, and does end up on disk unlike
 * `ExtensionAssetController`'s `{key}` -- but it can't contain `/` (Laravel's default route
 * segment regex already excludes it, so it can only ever name a file directly inside that
 * directory, never a nested path) and is explicitly rejected if it contains `..`; a `realpath()`
 * containment check against the registered directory backs both of those up.
 */
class DistAssetController
{
    public function __invoke(Manager $manager, string $key, string $filename): BinaryFileResponse
    {
        $dir = $manager->compiledAssetDirectories()->get($key);

        abort_if($dir === null || str_contains($filename, '..'), 404);

        $path = $dir.'/'.$filename;
        $real = realpath($path);

        abort_if($real === false || dirname($real) !== realpath($dir), 404);

        $mime = match (pathinfo($filename, PATHINFO_EXTENSION)) {
            'css' => 'text/css',
            'js' => 'application/javascript',
            default => 'application/octet-stream',
        };

        return response()->file($path, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
