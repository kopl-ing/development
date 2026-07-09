<?php

declare(strict_types=1);

namespace Kopling\Core\Storage;

/**
 * How long stored data is expected to live. Cache: safe to purge at any time, the app can
 * regenerate it. Persistent: durable, long-term data the app never regenerates on its own.
 */
enum StorageRetention: string
{
    case Cache = 'cache';
    case Persistent = 'persistent';
}
