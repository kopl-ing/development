<?php

declare(strict_types=1);

namespace Kopling\Example;

use Kopling\Core\Authorization\Permission;
use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\ChangesUx;
use Kopling\Core\Extension\Contract\HasPermissions;
use Kopling\Core\Extension\Contract\RequestsStorageDriver;
use Kopling\Core\Storage\StorageAccess;
use Kopling\Core\Storage\StoragePermission;
use Kopling\Core\Storage\StorageRequest;
use Kopling\Core\Storage\StorageRetention;
use Kopling\Core\Ux\Ux;

/**
 * A dummy extension -- not meant to be installed for real functionality. It exists so every
 * path and convention an extension can use has one working, verified example: see the
 * sibling directories (views/, css/, js/, migrations/, routes/, lang/, icon/) and
 * CLAUDE.md ("Extension conventions") for what each one does.
 */
class Extension extends AbstractExtension implements ChangesUx, RequestsStorageDriver, HasPermissions
{
    public static function name(): string
    {
        return 'Example';
    }

    public static function description(): string
    {
        return 'A dummy extension documenting every path and convention an extension can use.';
    }

    /**
     * Contracts are only needed for capabilities a directory convention can't express --
     * this one is illustrative only.
     *
     * @return array<StorageRequest>
     */
    public function storage(): array
    {
        return [
            new StorageRequest(
                id: 'avatars',
                label: 'Avatars',
                description: 'Profile pictures uploaded by members.',
                access: StorageAccess::Public,
                retention: StorageRetention::Persistent,
                permission: StoragePermission::ReadWrite,
            ),
        ];
    }

    /**
     * Written as just "manage-things" -- Manager prefixes it to "kopling-example::manage-things"
     * before it's registered, so this author never has to think about another extension's names.
     * label/description go through this extension's own lang/ (Section 4), same as any other
     * translatable string -- never a hardcoded string in the language the author happened to
     * write the extension in.
     *
     * @return array<Permission>
     */
    public function permissions(): array
    {
        return [
            new Permission(
                id: 'manage-things',
                label: __('kopling-example::permissions.manage-things.label'),
                description: __('kopling-example::permissions.manage-things.description'),
            ),
        ];
    }

    /**
     * Reuses core's generic <x-k::portal.navigation.item> rather than shipping a bespoke
     * component -- the common case. `.after('core::theme')` anchors this after Core's own
     * Theme link; `.when('manage-things')` reuses the permission declared above, prefixed
     * to "kopling-example::manage-things" by Manager the same way the entry's own id is.
     */
    public function ux(): Ux
    {
        return Ux::make()
            ->add('k::portal.navigation.item', ['label' => 'Hello', 'route' => 'example.hello'])
            ->in('core::side-navigation')
            ->as('hello')
            ->after('core::theme')
            ->when('manage-things');
    }
}
