<?php

declare(strict_types=1);

namespace Kopling\Core\Authorization;

/**
 * Core's own permissions, prefixed "core::" directly by core itself -- core isn't discovered
 * through Kopling\Core\Extension\Manager (it isn't a "kopling-extension"-typed package), so
 * it doesn't go through Manager::id()'s auto-prefixing the way an extension's do. There's no
 * collision risk to guard against here: core is singular, never installed twice.
 */
final class CorePermissions
{
    /**
     * @return array<Permission>
     */
    public static function all(): array
    {
        return [
            new Permission(
                id: 'core::manage-people',
                label: 'Manage people',
                description: 'Create, edit, and remove people and groups.',
            ),
        ];
    }
}
