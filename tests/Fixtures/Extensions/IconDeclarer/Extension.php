<?php

declare(strict_types=1);

namespace Tests\Fixtures\Extensions\IconDeclarer;

use Kopling\Core\Extend\Icon;
use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\HasIcons;

class Extension extends AbstractExtension implements HasIcons
{
    public static function name(): string
    {
        return 'Icon Declarer Fixture';
    }

    public static function description(): string
    {
        return 'Declares one icon with a Font Awesome default, for testing HasIcons.';
    }

    /**
     * @return array<Icon>
     */
    public function icons(): array
    {
        return [
            new Icon(id: 'widget', label: 'Widget', default: 'fas-cube'),
        ];
    }
}
