<?php

declare(strict_types=1);

namespace Kopling\Core\Extension\Contract;

interface HasCommands
{
    /**
     * @return array<class-string>
     */
    public function commands(): array;

}
