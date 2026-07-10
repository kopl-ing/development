<?php

declare(strict_types=1);

namespace Kopling\Core\Storage;

/**
 * One named storage purpose an extension needs, e.g. "avatars" or "attachment-thumbnails".
 * Declares behavior the purpose requires -- access, retention, permission -- never a
 * backend (local disk, S3, whatever): that choice belongs to the admin mapping this request
 * to a configured storage drive, not to the extension asking for it.
 */
class StorageRequest
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $description,
        public readonly StorageAccess $access,
        public readonly StorageRetention $retention,
        public readonly StoragePermission $permission,
    ) {
    }
}
