<?php

declare(strict_types=1);

namespace Kopling\Core\Extension\Contract;

interface HasModelRelations
{
    public function relations(): array;
}
