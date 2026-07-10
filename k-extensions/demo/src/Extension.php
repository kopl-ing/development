<?php

declare(strict_types=1);

namespace Kopling\Demo;

use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\HasCommands;

class Extension extends AbstractExtension implements HasCommands
{

    public static function name(): string
    {
        return 'Demo';
    }

    public static function description(): string
    {
        return 'Tooling for creating demo communities.';
    }

    public function commands(): array
    {

    }
}
