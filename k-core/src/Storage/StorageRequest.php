<?php

declare(strict_types=1);

namespace Kopling\Core\Storage;

/**
 * One named storage purpose an extension needs, e.g. "avatars" or "attachment-thumbnails".
 * Declares behavior the purpose requires -- access, retention, permission -- never a
 * backend (local disk, S3, whatever): that choice belongs to the admin mapping this request
 * to a configured storage drive, not to the extension asking for it.
 *
 * `$id` is set by the author as just the local part (e.g. "avatars"); Manager prefixes it
 * with the owning extension's id before it's exposed, same as Permission/Portal/UxEntry, so
 * two extensions can both declare an "avatars" purpose without colliding.
 */
class StorageRequest
{
    public function __construct(
        public string $id,
        public readonly string $label,
        public readonly string $description,
        public readonly StorageAccess $access,
        public readonly StorageRetention $retention,
        public readonly StoragePermission $permission,
    ) {
    }
}
