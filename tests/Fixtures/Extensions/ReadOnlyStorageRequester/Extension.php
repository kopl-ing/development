<?php

declare(strict_types=1);

namespace Tests\Fixtures\Extensions\ReadOnlyStorageRequester;

use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\RequestsStorageDriver;
use Kopling\Core\Storage\StorageAccess;
use Kopling\Core\Storage\StoragePermission;
use Kopling\Core\Storage\StorageRequest;
use Kopling\Core\Storage\StorageRetention;

class Extension extends AbstractExtension implements RequestsStorageDriver
{
    public static function name(): string
    {
        return 'Read-Only Storage Requester Fixture';
    }

    public static function description(): string
    {
        return 'Declares a read-only storage request, for testing Resolver\'s ReadOnlyFilesystemAdapter enforcement.';
    }

    /**
     * @return array<StorageRequest>
     */
    public function storage(): array
    {
        return [
            new StorageRequest(
                id: 'content',
                label: 'Content',
                description: 'Fixture read-only storage request.',
                access: StorageAccess::Private,
                retention: StorageRetention::Persistent,
                permission: StoragePermission::ReadOnly,
            ),
        ];
    }
}
