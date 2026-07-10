<?php

declare(strict_types=1);

namespace Kopling\Core\Storage;

/**
 * One named storage purpose an extension needs, e.g. "avatars" or "attachment-thumbnails".
 * Declares behavior the purpose requires -- access, retention, permission -- never a
 * backend (local disk, S3, whatever): that choice belongs to the admin mapping this request
 * to a configured storage drive, not to the extension asking for it.
 */
readonly class StorageRequest
{
    public function __construct(
        public string            $key,
        public string            $label,
        public string            $description,
        public StorageAccess     $access,
        public StorageRetention  $retention,
        public StoragePermission $permission,
    ) {
    }
}
