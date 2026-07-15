<?php

declare(strict_types=1);

namespace Tests\Fixtures\Extensions\IconPackDeclarer;

use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\ChangesIcons;

class Extension extends AbstractExtension implements ChangesIcons
{
    public static function name(): string
    {
        return 'Icon Pack Declarer Fixture';
    }

    public static function description(): string
    {
        return 'Maps a foreign icon id to its own icon, for testing ChangesIcons.';
    }

    /**
     * @return array<string, string>
     */
    public function iconMap(): array
    {
        return [
            'tests-fixtures-icon-declarer::widget' => 'fas-square',
            'tests-fixtures-icon-declarer::not-installed' => 'fas-circle',
        ];
    }
}
