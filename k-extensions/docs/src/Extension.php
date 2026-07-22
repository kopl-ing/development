<?php

declare(strict_types=1);

namespace Kopling\Docs;

use Kopling\Core\Extend\Ux;
use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\ChangesUx;
use Kopling\Core\Extension\Contract\ExtendsPortals;
use Kopling\Core\Extension\Contract\HasCommands;
use Kopling\Core\Extension\Contract\HasPortals;
use Kopling\Core\Extension\Contract\RequestsStorageDriver;
use Kopling\Core\Portal\Portal;
use Kopling\Core\Portal\PortalExtension;
use Kopling\Core\Storage\StorageAccess;
use Kopling\Core\Storage\StoragePermission;
use Kopling\Core\Storage\StorageRequest;
use Kopling\Core\Storage\StorageRetention;
use Kopling\Core\Ux\Community\UserMenu;
use Kopling\Docs\Console\SyncDocs;
use Kopling\Docs\Ux\Sidebar;

/**
 * A reusable multi-page docs site -- content is Markdown + YAML front matter files on a
 * Storage-resolved drive (`kopling-docs::content`, declared read-only), indexed into
 * `docs_pages` by `PageRegistry::sync()` (`kopling:docs:sync`), never a per-request filesystem
 * walk. Reuses `<x-k::community.chrome>` for its layout -- same non-community, non-composer
 * surface shape Style Guide already proves that component handles
 * (`:composer-slot="null" :mobile-dock="false"`).
 */
class Extension extends AbstractExtension implements HasPortals, ExtendsPortals, RequestsStorageDriver, HasCommands, ChangesUx
{
    public static function name(): string
    {
        return 'Docs';
    }

    public static function description(): string
    {
        return 'A reusable multi-page docs site, built from Markdown + front matter files on a Storage-resolved drive.';
    }

    /**
     * @return array<Portal>
     */
    public function portals(): array
    {
        return [
            new Portal(
                id: 'docs',
                label: 'Docs',
                path: 'docs',
                layout: 'kopling-docs::layouts.docs',
            ),
        ];
    }

    /**
     * @return array<PortalExtension>
     */
    public function extendsPortals(): array
    {
        return [
            new PortalExtension('kopling-docs::docs')
                ->routes(__DIR__.'/../routes/web.php'),
        ];
    }

    /**
     * @return array<StorageRequest>
     */
    public function storage(): array
    {
        return [
            new StorageRequest(
                id: 'content',
                label: 'Docs content',
                description: 'Markdown + front matter files for the docs site. Read-only -- authored outside the app (git, an admin-managed sync, etc.), never written to by Docs itself.',
                access: StorageAccess::Private,
                retention: StorageRetention::Persistent,
                permission: StoragePermission::ReadOnly,
            ),
        ];
    }

    /**
     * @return array<class-string>
     */
    public function commands(): array
    {
        return [SyncDocs::class];
    }

    public function ux(): Ux
    {
        return Ux::make()
            ->add(Sidebar::class)
            ->in('kopling-docs::docs.sidebar-panel')
            ->as('sidebar')
            ->add(UserMenu::class)
            ->in('kopling-docs::docs.topbar')
            ->as('user-menu');
    }
}
