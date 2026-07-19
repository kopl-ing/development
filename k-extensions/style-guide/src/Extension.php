<?php

declare(strict_types=1);

namespace Kopling\StyleGuide;

use Kopling\Core\Extend\Permission;
use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\ExtendsPortals;
use Kopling\Core\Extension\Contract\HasPermissions;
use Kopling\Core\Extension\Contract\HasPortals;
use Kopling\Core\Portal\Portal;
use Kopling\Core\Portal\PortalExtension;

/**
 * A design-system showcase, not a "real" feature -- its own Portal (gated by
 * `access-style-guide`) rather than a page bolted onto Community/Admin, so it can be disabled
 * per-install like anything else without touching either. Deliberately not `CannotBeDisabled`:
 * unlike Core, there's nothing structural that breaks if a host disables it. See
 * `tests/Feature/StyleGuide/ComponentCoverageTest.php` for the mechanism that keeps this
 * extension's own showcase honest as core's `<x-k::*>` inventory grows -- the enforcement, not
 * this class, is the point.
 */
class Extension extends AbstractExtension implements HasPortals, ExtendsPortals, HasPermissions
{
    public static function name(): string
    {
        return 'Style Guide';
    }

    public static function description(): string
    {
        return "A living showcase of every core <x-k::*> component, kept in sync by a coverage test rather than hand-maintained docs.";
    }

    /**
     * @return array<Portal>
     */
    public function portals(): array
    {
        return [
            new Portal(
                id: 'style-guide',
                label: 'Style Guide',
                path: 'style-guide',
                layout: 'kopling-style-guide::layouts.style-guide',
                permission: 'access-style-guide',
            ),
        ];
    }

    /**
     * @return array<Permission>
     */
    public function permissions(): array
    {
        return [
            new Permission(
                id: 'access-style-guide',
                label: __('kopling-style-guide::permissions.access-style-guide.label'),
                description: __('kopling-style-guide::permissions.access-style-guide.description'),
            ),
        ];
    }

    /**
     * @return array<PortalExtension>
     */
    public function extendsPortals(): array
    {
        return [
            new PortalExtension('kopling-style-guide::style-guide')
                ->routes(__DIR__.'/../routes/web.php'),
        ];
    }
}
