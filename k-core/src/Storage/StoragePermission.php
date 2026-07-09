<?php

declare(strict_types=1);

namespace Kopling\Core\Storage;

/**
 * Whether the requesting extension ever writes to this storage, or only reads
 * pre-existing/vendored files -- least-privilege, so a driver mapping never grants
 * write access a purpose will never use.
 */
enum StoragePermission: string
{
    case ReadOnly = 'read_only';
    case ReadWrite = 'read_write';
}
