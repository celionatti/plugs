<?php

declare(strict_types=1);

namespace Plugs\Cache\Drivers;

use Plugs\Cache\CacheDriverInterface;

class FileCacheDriver implements CacheDriverInterface
{
    private string $cachePath;

    public function __construct(string $cachePath = null)
    {
        if ($cachePath === null) {
            $cachePath = defined('STORAGE_PATH')
                ? STORAGE_PATH . 'cache'
                : __DIR__ . '/../../../storage/cache';
        }

        $this->cachePath = rtrim($cachePath, '/\\');
        $this->ensureDirectoryExists();
    }

    public function get(string $key, $default = null)
    {
        $file = $this->getFilePath($key);

        if (!file_exists($file)) {
            return $default;
        }

        $content = file_get_contents($file);
        if ($content === false) {
            return $default;
        }

        $data = unserialize($content);

        if ($data['expires'] !== 0 && $data['expires'] < time()) {
            $this->delete($key);
            return $default;
        }

        return $data['value'];
    }

    public function set(string $key, $value, int|null $ttl = null): bool
    {
        $file = $this->getFilePath($key);

        $data = [
            'value' => $value,
            'expires' => $ttl === null ? 0 : time() + $ttl,
            'created' => time()
        ];

        return file_put_contents($file, serialize($data), LOCK_EX) !== false;
    }

    public function delete(string $key): bool
    {
        $file = $this->getFilePath($key);

        if (file_exists($file)) {
            return unlink($file);
        }

        return true;
    }

    public function clear(): bool
    {
        $files = glob($this->cachePath . DIRECTORY_SEPARATOR . '*.cache');
        $success = true;

        foreach ($files as $file) {
            if (is_file($file)) {
                $success = $success && unlink($file);
            }
        }

        return $success;
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function getMultiple(iterable $keys, $default = null): iterable
    {
        $results = [];
        foreach ($keys as $key) {
            $results[$key] = $this->get($key, $default);
        }
        return $results;
    }

    public function setMultiple(iterable $values, int|null $ttl = null): bool
    {
        $success = true;
        foreach ($values as $key => $value) {
            $success = $success && $this->set($key, $value, $ttl);
        }
        return $success;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        $success = true;
        foreach ($keys as $key) {
            $success = $success && $this->delete($key);
        }
        return $success;
    }

    private function getFilePath(string $key): string
    {
        return $this->cachePath . DIRECTORY_SEPARATOR . md5($key) . '.cache';
    }

    private function ensureDirectoryExists(): void
    {
        if (!is_dir($this->cachePath)) {
            mkdir($this->cachePath, 0755, true);
        }
    }
}
