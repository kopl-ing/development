<?php

declare(strict_types=1);

namespace Tests\Fixtures\Extensions\PermissionDeclarer;

use Kopling\Core\Extend\Permission;
use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\HasPermissions;

class Extension extends AbstractExtension implements HasPermissions
{
    public static function name(): string
    {
        return 'Permission Declarer Fixture';
    }

    public static function description(): string
    {
        return 'Declares permissions, for testing HasPermissions\'s id prefixing.';
    }

    /**
     * @return array<Permission>
     */
    public function permissions(): array
    {
        return [
            new Permission(
                id: 'manage-widgets',
                label: 'Manage widgets',
                description: 'Fixture permission with no default and no callback.',
            ),
        ];
    }
}
