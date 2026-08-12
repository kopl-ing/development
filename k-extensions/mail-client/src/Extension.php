<?php

declare(strict_types=1);

namespace Kopling\MailClient;

use Kopling\Core\Extend\Permission;
use Kopling\Core\Extend\Ux;
use Kopling\Core\Extend\Ux\ProvidesUxEntries;
use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\ChangesUx;
use Kopling\Core\Extension\Contract\ExtendsPortals;
use Kopling\Core\Extension\Contract\HasCommands;
use Kopling\Core\Extension\Contract\HasPermissions;
use Kopling\Core\Extension\Contract\HasPortals;
use Kopling\Core\Portal\Portal;
use Kopling\Core\Portal\PortalExtension;
use Kopling\Core\Ux\Community\UserMenu;
use Kopling\Core\Ux\Portal\Navigation\Item;
use Kopling\MailClient\Console\SyncPendingCommand;
use Kopling\MailClient\Ux\Sidebar;

class Extension extends AbstractExtension implements ChangesUx, ExtendsPortals, HasCommands, HasPermissions, HasPortals
{
    public static function name(): string
    {
        return 'Mail';
    }

    public static function description(): string
    {
        return 'A webmail Portal connecting to any existing IMAP/POP3 mailbox.';
    }

    /**
     * @return array<Permission>
     */
    public function permissions(): array
    {
        return [
            new Permission(
                id: 'access-mail',
                label: __('kopling-mail-client::permissions.access-mail.label'),
                description: __('kopling-mail-client::permissions.access-mail.description'),
            ),
        ];
    }

    /**
     * @return array<Portal>
     */
    public function portals(): array
    {
        return [
            new Portal(
                id: 'mail',
                label: 'Mail',
                path: 'mail',
                layout: 'kopling-mail-client::layouts.mail',
                permission: 'access-mail',
            ),
        ];
    }

    /**
     * @return array<PortalExtension>
     */
    public function extendsPortals(): array
    {
        return [
            new PortalExtension('kopling-mail-client::mail')
                ->routes(__DIR__.'/../routes/web.php')
                ->css(__DIR__.'/../resources/css/app.css')
                ->js(__DIR__.'/../resources/js/app.js')
                ->compiledAssets(__DIR__.'/..'),
        ];
    }

    /**
     * Reuses the exact same avatar dropdown Community's chrome renders (`UserMenu::class` into
     * this portal's own topbar slot, same as Admin's `layouts/admin.blade.php`), and the same
     * entry-into-user-menu pattern Admin uses for its own link back to itself, gated by the same
     * `access-mail` permission that gates the Portal's own routes above.
     */
    public function ux(): ProvidesUxEntries
    {
        return Ux::make()
            ->add(Item::class, [
                'label' => __('kopling-mail-client::messages.mail'),
                'route' => 'kopling-mail-client::mail/index',
                'hideOnPortal' => 'kopling-mail-client::mail',
            ])
            ->in(UserMenu::SLOT)
            ->as('mail-link')
            ->when('access-mail')
            ->add(UserMenu::class)
            ->in('kopling-mail-client::mail.topbar')
            ->as('user-menu')
            ->add(Sidebar::class)
            ->in('kopling-mail-client::mail.sidebar-panel')
            ->as('sidebar');
    }

    /**
     * The degraded-host fallback for hosts with no real queue worker -- see
     * Console\SyncPendingCommand's own docblock.
     *
     * @return array<class-string>
     */
    public function commands(): array
    {
        return [SyncPendingCommand::class];
    }
}
