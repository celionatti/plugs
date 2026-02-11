<?php

namespace Plugs\Filesystem\Drivers;

use Plugs\Filesystem\FilesystemDriverInterface;

class LocalFilesystemDriver implements FilesystemDriverInterface
{
    protected string $root;
    protected string $url;
    protected string $visibility;

    public function __construct(array $config)
    {
        $this->root = rtrim($config['root'], DIRECTORY_SEPARATOR);
        $this->url = isset($config['url']) ? rtrim($config['url'], '/') : '';
        $this->visibility = $config['visibility'] ?? 'public';
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
        $path = ltrim($path, '/');

        if ($this->url) {
            return $this->url . '/' . $path;
        }

        return '/storage/' . $path;
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

    public function fullPath(string $path): string
    {
        $path = $this->ensureValidPath($path);

        return $this->root . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
    }

    /**
     * Ensure the path is valid and within the root directory.
     *
     * @param string $path
     * @return string
     * @throws \RuntimeException
     */
    protected function ensureValidPath(string $path): string
    {
        // Remove null bytes
        $path = str_replace(chr(0), '', $path);

        // Normalize separators
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

        // Check for path traversal attempts
        if (str_contains($path, '..' . DIRECTORY_SEPARATOR) || str_ends_with($path, '..')) {
            throw new \RuntimeException("Path traversal attempt detected: {$path}");
        }

        // Prevent absolute paths that might point outside the root
        if ($this->isAbsolutePath($path)) {
            // If it's an absolute path, it MUST start with the root
            if (!str_starts_with($path, $this->root)) {
                throw new \RuntimeException("Absolute path outside of root detected: {$path}");
            }
            // Strip the root to get the relative path for internal use
            return ltrim(str_replace($this->root, '', $path), DIRECTORY_SEPARATOR);
        }

        return $path;
    }

    /**
     * Check if a path is absolute.
     *
     * @param string $path
     * @return bool
     */
    protected function isAbsolutePath(string $path): bool
    {
        if (DIRECTORY_SEPARATOR === '/') {
            return str_starts_with($path, '/');
        }

        // Windows absolute path (e.g., C:\ or \\)
        return preg_match('/^[a-zA-Z]:\\\\/', $path) || str_starts_with($path, '\\\\');
    }

    public function path(string $absolutePath): string
    {
        return ltrim(str_replace($this->root, '', $absolutePath), DIRECTORY_SEPARATOR);
    }

    public function copy(string $from, string $to): bool
    {
        return copy($this->fullPath($from), $this->fullPath($to));
    }

    public function move(string $from, string $to): bool
    {
        return rename($this->fullPath($from), $this->fullPath($to));
    }

    public function getVisibility(string $path): string
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            return 'public'; // Windows filesystem emulation is limited
        }

        $permissions = fileperms($this->fullPath($path));

        return ($permissions & 0x01FF) === 0644 ? 'public' : 'private';
    }

    public function setVisibility(string $path, string $visibility): bool
    {
        $permissions = $visibility === 'public' ? 0644 : 0600;

        return chmod($this->fullPath($path), $permissions);
    }

    public function mimeType(string $path): string
    {
        return mime_content_type($this->fullPath($path));
    }

    public function append(string $path, string $data): bool
    {
        return file_put_contents($this->fullPath($path), $data, FILE_APPEND) !== false;
    }

    public function prepend(string $path, string $data): bool
    {
        if ($this->exists($path)) {
            return $this->put($path, $data . $this->get($path));
        }

        return $this->put($path, $data);
    }

    public function download(string $path, ?string $name = null, array $headers = [])
    {
        if (!$this->exists($path)) {
            throw new \RuntimeException("File not found at path: {$path}");
        }

        $response = new \Plugs\Http\Message\Response();
        $stream = new \Plugs\Http\Message\Stream(fopen($this->fullPath($path), 'rb'));

        $filename = $name ?? basename($path);

        $response = $response
            ->withBody($stream)
            ->withHeader('Content-Type', mime_content_type($this->fullPath($path)))
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->withHeader('Content-Length', (string) $this->size($path));

        foreach ($headers as $key => $value) {
            $response = $response->withHeader($key, $value);
        }

        return $response;
    }
}
