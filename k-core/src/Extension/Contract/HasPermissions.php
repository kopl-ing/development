<?php

declare(strict_types=1);

namespace Kopling\Core\Extension\Contract;

use Kopling\Core\Authorization\Permission;

interface HasPermissions
{
    /**
     * @return array<Permission>
     */
    public function permissions(): array;
}
