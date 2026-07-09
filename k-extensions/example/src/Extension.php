<?php

declare(strict_types=1);

namespace Kopling\Example;

use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\RequestsStorageDriver;
use Kopling\Core\Storage\StorageRequest;

/**
 * A dummy extension -- not meant to be installed for real functionality. It exists so every
 * path and convention an extension can use has one working, verified example: see the
 * sibling directories (views/, css/, js/, migrations/, routes/, lang/, icon/) and
 * CLAUDE.md ("Extension conventions") for what each one does.
 */
class Extension extends AbstractExtension implements RequestsStorageDriver
{
    public static function name(): string
    {
        return 'Example';
    }

    public static function description(): string
    {
        return 'A dummy extension documenting every path and convention an extension can use.';
    }

    /**
     * Contracts are only needed for capabilities a directory convention can't express --
     * this one is illustrative only, since StorageRequest itself isn't fleshed out yet.
     *
     * @return array<StorageRequest>
     */
    public function storage(): array
    {
        return [new StorageRequest()];
    }
}
