<?php

declare(strict_types=1);

namespace Kopling\Reactions;

use Kopling\Core\Extension\AbstractExtension;

class Extension extends AbstractExtension
{
    public static function name(): string
    {
        return 'Reactions';
    }

    public static function description(): string
    {
        return 'Lightweight reactions for posts.';
    }
}
