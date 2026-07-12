<?php

declare(strict_types=1);

namespace Tests\Fixtures\Extensions\StorageRequester;

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
        return 'Storage Requester Fixture';
    }

    public static function description(): string
    {
        return 'Declares a storage request, for testing RequestsStorageDriver\'s id prefixing/grouping.';
    }

    /**
     * @return array<StorageRequest>
     */
    public function storage(): array
    {
        return [
            new StorageRequest(
                id: 'attachments',
                label: 'Attachments',
                description: 'Fixture storage request.',
                access: StorageAccess::Public,
                retention: StorageRetention::Persistent,
                permission: StoragePermission::ReadWrite,
            ),
        ];
    }
}
