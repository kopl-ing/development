<?php

declare(strict_types=1);

namespace Tests\Fixtures\Extensions\SettingsDeclarer;

use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\HasSettings;
use Kopling\Core\Ux\Form\Field;
use Kopling\Core\Ux\Form\Toggle;

class Extension extends AbstractExtension implements HasSettings
{
    public static function name(): string
    {
        return 'Settings Declarer Fixture';
    }

    public static function description(): string
    {
        return 'Declares one per-person settings field, for testing HasSettings.';
    }

    /**
     * @return array<Field>
     */
    public function settings(): array
    {
        return [
            new Field(id: 'compact', label: 'Compact', component: Toggle::class, default: false),
        ];
    }
}
