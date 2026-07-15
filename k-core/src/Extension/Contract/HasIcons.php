<?php

declare(strict_types=1);

namespace Kopling\Core\Extension\Contract;

use Kopling\Core\Extend\Icon;

interface HasIcons
{
    /**
     * @return array<Icon>
     */
    public function icons(): array;
}
