<?php

declare(strict_types=1);

namespace Kopling\Mcp;

use Kopling\Core\Extend\Permission;
use Kopling\Core\Extend\Ux;
use Kopling\Core\Extend\Ux\ProvidesUxEntries;
use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\ChangesUx;
use Kopling\Core\Extension\Contract\ExtendsPortals;
use Kopling\Core\Extension\Contract\HasAdminSettings;
use Kopling\Core\Extension\Contract\HasPermissions;
use Kopling\Core\Portal\PortalExtension;
use Kopling\Core\Ux\Form\Field;
use Kopling\Core\Ux\Form\Input;
use Kopling\Core\Ux\Form\TextArea;
use Kopling\Core\Ux\Portal\Navigation\Item;

class Extension extends AbstractExtension implements ChangesUx, ExtendsPortals, HasAdminSettings, HasPermissions
{
    public static function name(): string
    {
        return 'MCP';
    }

    public static function description(): string
    {
        return 'Lets AI assistants work with this site on a person\'s behalf, through tools other extensions contribute.';
    }

    public function permissions(): array
    {
        return [
            new Permission(
                id: 'use-mcp',
                label: __('kopling-mcp::permissions.use-mcp.label'),
                description: __('kopling-mcp::permissions.use-mcp.description'),
            ),
            new Permission(
                id: 'view-tool-calls',
                label: __('kopling-mcp::permissions.view-tool-calls.label'),
                description: __('kopling-mcp::permissions.view-tool-calls.description'),
            ),
        ];
    }

    /**
     * @return array<PortalExtension>
     */
    public function extendsPortals(): array
    {
        return [
            (new PortalExtension('kopling-admin::admin'))->routes(__DIR__.'/../routes/admin.php'),
        ];
    }

    public function ux(): ProvidesUxEntries
    {
        return Ux::make()
            ->add(Item::class, [
                'label' => __('kopling-mcp::messages.admin.nav'),
                'route' => 'kopling-admin::admin/mcp',
            ])
            ->in('kopling-admin::admin.navigation')
            ->as('admin-nav')
            ->when('kopling-mcp::view-tool-calls');
    }

    /**
     * @return array<Field>
     */
    public function adminSettings(): array
    {
        return [
            new Field(
                id: 'redirect-domains',
                label: 'Allowed redirect domains',
                component: TextArea::class,
                default: '*',
                description: 'Where apps registering themselves may send people back after sign-in, one per line, e.g. "https://claude.ai". "*" allows any; localhost always works. A person still has to approve every app.',
            ),
            new Field(
                id: 'rate-limit',
                label: 'Tool calls per minute',
                component: Input::class,
                default: '60',
                description: 'Per person and app.',
                data: ['type' => 'number'],
            ),
            new Field(
                id: 'audit-retention-days',
                label: 'Keep the tool call log for (days)',
                component: Input::class,
                default: '90',
                description: 'Older entries are removed.',
                data: ['type' => 'number'],
            ),
        ];
    }
}
