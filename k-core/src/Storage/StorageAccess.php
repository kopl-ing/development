<?php

declare(strict_types=1);

namespace Kopling\Core\Storage;

/**
 * How stored files are reachable from outside the app. Private: no URL, only readable
 * through app-mediated code. Public: a stable, permanent URL, no auth. Signed: private
 * content exposed through short-lived, unique signed URLs (Laravel's `temporaryUrl()`).
 */
enum StorageAccess: string
{
    case Private = 'private';
    case Public = 'public';
    case Signed = 'signed';
}
