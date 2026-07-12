<?php

declare(strict_types=1);

namespace Tests\Fixtures\Extensions\PortalAttacher;

use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\ExtendsPortals;
use Kopling\Core\Portal\PortalExtension;

/**
 * A fixture extension that attaches to *another* extension's Portal (PortalOwner's "demo"), plus
 * one that was never declared by anything -- exercises `ExtendsPortals` targeting a foreign
 * Portal, and the graceful-degradation behaviour for a dangling target.
 */
class Extension extends AbstractExtension implements ExtendsPortals
{
    public static function name(): string
    {
        return 'Portal Attacher Fixture';
    }

    public static function description(): string
    {
        return 'Attaches to another extension\'s Portal, and to one that was never declared.';
    }

    /**
     * @return array<PortalExtension>
     */
    public function extendsPortals(): array
    {
        return [
            new PortalExtension('tests-fixtures-portal-owner::demo')
                ->routes(__DIR__.'/routes.php'),
            new PortalExtension('tests-fixtures-nonexistent::ghost')
                ->routes(__DIR__.'/routes.php'),
        ];
    }
}
