<?php

declare(strict_types=1);

namespace Kopling\Core\Storage;

use Illuminate\Contracts\Filesystem\Filesystem;

/**
 * Enforces a `StorageRequest`'s declared `StoragePermission::ReadOnly` structurally, not just by
 * convention -- same "structurally cannot" posture `Ux\Editor\DocumentRenderer` already uses for
 * node whitelisting. Wraps a real resolved disk and throws on every write method, regardless of
 * the underlying `Drive`'s own `writable` flag -- a `writable` drive can still host a
 * `ReadOnly`-declared purpose; the two are independent (see `Resolver`).
 */
class ReadOnlyFilesystemAdapter implements Filesystem
{
    public function __construct(protected Filesystem $disk)
    {
    }

    public function path($path)
    {
        return $this->disk->path($path);
    }

    public function exists($path)
    {
        return $this->disk->exists($path);
    }

    public function get($path)
    {
        return $this->disk->get($path);
    }

    public function readStream($path)
    {
        return $this->disk->readStream($path);
    }

    public function getVisibility($path)
    {
        return $this->disk->getVisibility($path);
    }

    public function size($path)
    {
        return $this->disk->size($path);
    }

    public function lastModified($path)
    {
        return $this->disk->lastModified($path);
    }

    public function files($directory = null, $recursive = false)
    {
        return $this->disk->files($directory, $recursive);
    }

    public function allFiles($directory = null)
    {
        return $this->disk->allFiles($directory);
    }

    public function directories($directory = null, $recursive = false)
    {
        return $this->disk->directories($directory, $recursive);
    }

    public function allDirectories($directory = null)
    {
        return $this->disk->allDirectories($directory);
    }

    public function put($path, $contents, $options = [])
    {
        throw $this->readOnly();
    }

    public function putFile($path, $file = null, $options = [])
    {
        throw $this->readOnly();
    }

    public function putFileAs($path, $file, $name = null, $options = [])
    {
        throw $this->readOnly();
    }

    public function writeStream($path, $resource, array $options = [])
    {
        throw $this->readOnly();
    }

    public function setVisibility($path, $visibility)
    {
        throw $this->readOnly();
    }

    public function prepend($path, $data)
    {
        throw $this->readOnly();
    }

    public function append($path, $data)
    {
        throw $this->readOnly();
    }

    public function delete($paths)
    {
        throw $this->readOnly();
    }

    public function copy($from, $to)
    {
        throw $this->readOnly();
    }

    public function move($from, $to)
    {
        throw $this->readOnly();
    }

    public function makeDirectory($path)
    {
        throw $this->readOnly();
    }

    public function deleteDirectory($directory)
    {
        throw $this->readOnly();
    }

    protected function readOnly(): \RuntimeException
    {
        return new \RuntimeException('This storage request is declared read-only; the resolved drive refuses writes.');
    }
}
