<?php

declare(strict_types=1);

namespace Tests\Support;

use Kopling\Core\Extension\Manifest;

/**
 * Stands in for the real `Manifest` (which reads `vendor/composer/installed.json`) so a test
 * can control exactly which extensions `Manager::extensions()` discovers, without needing a real
 * Composer package for every fixture -- deliberately skips the parent constructor (`file_exists`/
 * cache-path plumbing `PackageManifest` needs) since `extensions()` is the only method this ever
 * gets asked for.
 */
class FakeManifest extends Manifest
{
    /**
     * @param  array<string, array{namespace: string, path: string}>  $extensions
     */
    public function __construct(protected array $extensions = [])
    {
    }

    /**
     * @return array<string, array{namespace: string, path: string}>
     */
    public function extensions(): array
    {
        return $this->extensions;
    }
}
