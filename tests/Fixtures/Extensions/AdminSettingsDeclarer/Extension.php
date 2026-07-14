<?php

declare(strict_types=1);

namespace Tests\Fixtures\Extensions\AdminSettingsDeclarer;

use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\HasAdminSettings;
use Kopling\Core\Ux\Form\Field;
use Kopling\Core\Ux\Form\Toggle;

/**
 * Declares one field, using the real `Toggle` component (already core, needs no view of its
 * own to set up) -- so both the pure aggregation tests (ManagerAdminSettingsTest) and the HTTP
 * rendering/save tests (SettingsControllerTest) can share one fixture.
 */
class Extension extends AbstractExtension implements HasAdminSettings
{
    public static function name(): string
    {
        return 'Admin Settings Declarer Fixture';
    }

    public static function description(): string
    {
        return 'Declares one admin settings field, for testing HasAdminSettings.';
    }

    /**
     * @return array<Field>
     */
    public function adminSettings(): array
    {
        return [
            new Field(
                id: 'enabled',
                label: 'Enabled',
                component: Toggle::class,
                default: true,
                description: 'Whether the fixture thing is enabled.',
            ),
        ];
    }
}
