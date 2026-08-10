<?php

declare(strict_types=1);

namespace Kopling\Core\Extension\Contract;

use Kopling\Core\Extend\Federation;

interface HasFederatedModels
{
    /**
     * @return array<Federation>
     */
    public function federatedModels(): array;
}
