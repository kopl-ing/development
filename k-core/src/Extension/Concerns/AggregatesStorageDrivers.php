<?php

declare(strict_types=1);

namespace Kopling\Core\Extension\Concerns;

use Kopling\Core\Extension\Contract\RequestsStorageDriver;
use Kopling\Core\Storage\StorageRequest;

trait AggregatesStorageDrivers
{
    /**
     * @return array<string, array<StorageRequest>>
     */
    public function storageDrivers(): array
    {
        if (($cached = $this->cache->get()) !== null) {
            return collect($cached['storageDrivers'])
                ->map(fn (array $requests) => array_map(fn (array $data) => StorageRequest::fromArray($data), $requests))
                ->all();
        }

        $requests = [];

        foreach ($this->extensions() as $package => $extension) {
            if (! $extension instanceof RequestsStorageDriver) {
                continue;
            }

            $prefix = $this->id($package).'::';
            $declared = collect($extension->storage())->ensure(StorageRequest::class);

            foreach ($declared as $request) {
                $request->id = $prefix.$request->id;
            }

            $requests[$this->id($package)] = $declared->all();
        }

        return $requests;
    }
}
