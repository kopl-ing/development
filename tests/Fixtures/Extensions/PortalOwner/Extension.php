<?php

declare(strict_types=1);

namespace Tests\Fixtures\Extensions\PortalOwner;

use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\ExtendsPortals;
use Kopling\Core\Extension\Contract\HasPortals;
use Kopling\Core\Portal\Portal;
use Kopling\Core\Portal\PortalExtension;

/**
 * A fixture extension that declares a Portal ("demo") and attaches its own routes to it -- the
 * baseline `HasPortals`/`ExtendsPortals` scenario `Manager`'s portal-related tests exercise.
 */
class Extension extends AbstractExtension implements HasPortals, ExtendsPortals
{
    public static function name(): string
    {
        return 'Portal Owner Fixture';
    }

    public static function description(): string
    {
        return 'Declares a Portal and attaches routes to it, for testing HasPortals/ExtendsPortals.';
    }

    /**
     * @return array<Portal>
     */
    public function portals(): array
    {
        return [
            new Portal(
                id: 'demo',
                label: 'Demo',
                path: 'fixture-demo',
                layout: 'not-rendered-in-these-tests',
                permission: 'access-demo',
            ),
        ];
    }

    /**
     * @return array<PortalExtension>
     */
    public function extendsPortals(): array
    {
        return [
            new PortalExtension('tests-fixtures-portal-owner::demo')
                ->routes(__DIR__.'/routes.php'),
        ];
    }
}
