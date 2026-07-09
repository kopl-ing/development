<?php

declare(strict_types=1);

namespace Kopling\Core\Extension\Contract;

use Kopling\Core\Storage\StorageRequest;

interface RequestsStorageDriver
{
    /**
     * @return array<StorageRequest>
     */
    public function storage(): array;
}
