<?php

namespace Plugs\Filesystem\Drivers;

use Plugs\Filesystem\FilesystemDriverInterface;

class LocalFilesystemDriver implements FilesystemDriverInterface
{
    protected string $root;

    public function __construct(array $config)
    {
        $this->root = rtrim($config['root'], DIRECTORY_SEPARATOR);
    }

    public function exists(string $path): bool
    {
        return file_exists($this->fullPath($path));
    }

    public function get(string $path): ?string
    {
        if (!$this->exists($path)) {
            return null;
        }

        return file_get_contents($this->fullPath($path));
    }

    public function put(string $path, string $contents): bool
    {
        $fullPath = $this->fullPath($path);
        $directory = dirname($fullPath);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return file_put_contents($fullPath, $contents) !== false;
    }

    public function delete(string $path): bool
    {
        if ($this->exists($path)) {
            return unlink($this->fullPath($path));
        }

        return false;
    }

    public function url(string $path): string
    {
        // This is a basic implementation. In a real app, you'd map this to a public URL.
        return '/storage/' . ltrim($path, '/');
    }

    public function size(string $path): int
    {
        return filesize($this->fullPath($path));
    }

    public function lastModified(string $path): int
    {
        return filemtime($this->fullPath($path));
    }

    public function makeDirectory(string $path): bool
    {
        return mkdir($this->fullPath($path), 0755, true);
    }

    public function deleteDirectory(string $path): bool
    {
        $fullPath = $this->fullPath($path);

        if (!is_dir($fullPath)) {
            return false;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($fullPath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getRealPath());
            } else {
                unlink($item->getRealPath());
            }
        }

        return rmdir($fullPath);
    }

    protected function fullPath(string $path): string
    {
        return $this->root . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
    }
}
