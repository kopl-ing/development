<?php

declare(strict_types=1);

namespace Kopling\Core\Extension\Contract;

use Kopling\Core\Extend\Model;

interface ExtendsModels
{
    /**
     * @return array<Model>
     */
    public function models(): array;
}
