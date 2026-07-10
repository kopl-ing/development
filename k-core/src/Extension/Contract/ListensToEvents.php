<?php

declare(strict_types=1);

namespace Kopling\Core\Extension\Contract;

interface ListensToEvents
{
    /**
     * @return array<class-string|class-string<class-string>>
     */
    public function listen(): array;
}
