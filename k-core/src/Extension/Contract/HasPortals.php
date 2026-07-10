<?php

declare(strict_types=1);

namespace Kopling\Core\Extension\Contract;

use Kopling\Core\Portal\Portal;

interface HasPortals
{
    /**
     * @return array<Portal>
     */
    public function portals(): array;
}
