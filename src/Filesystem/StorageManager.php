<?php

namespace Plugs\Filesystem;

use InvalidArgumentException;
use Plugs\Filesystem\Drivers\LocalFilesystemDriver;

class StorageManager
{
    protected array $disks = [];
    protected array $config;
    protected array $customCreators = [];

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function disk(?string $name = null): FilesystemDriverInterface
    {
        $name = $name ?: $this->getDefaultDriver();

        if (!isset($this->disks[$name])) {
            $this->disks[$name] = $this->resolve($name);
        }

        return $this->disks[$name];
    }

    protected function resolve(string $name): FilesystemDriverInterface
    {
        $config = $this->getConfig($name);

        if (is_null($config)) {
            throw new InvalidArgumentException("Disk [{$name}] is not defined.");
        }

        if (isset($this->customCreators[$config['driver']])) {
            return $this->callCustomCreator($config);
        }

        $driverMethod = 'create' . ucfirst($config['driver']) . 'Driver';

        if (method_exists($this, $driverMethod)) {
            return $this->{$driverMethod}($config);
        }

        throw new InvalidArgumentException("Driver [{$config['driver']}] is not supported.");
    }

    protected function createLocalDriver(array $config): LocalFilesystemDriver
    {
        return new LocalFilesystemDriver($config);
    }

    public function getDefaultDriver(): string
    {
        return $this->config['default'];
    }

    protected function getConfig(string $name): ?array
    {
        return $this->config['disks'][$name] ?? null;
    }

    public function extend(string $driver, \Closure $callback): void
    {
        $this->customCreators[$driver] = $callback;
    }

    public function exists(string $path): bool
    {
        return $this->disk()->exists($path);
    }

    public function get(string $path): ?string
    {
        return $this->disk()->get($path);
    }

    public function put(string $path, string $contents): bool
    {
        return $this->disk()->put($path, $contents);
    }

    public function delete(string $path): bool
    {
        return $this->disk()->delete($path);
    }

    public function url(string $path): string
    {
        return $this->disk()->url($path);
    }

    public function size(string $path): int
    {
        return $this->disk()->size($path);
    }

    public function lastModified(string $path): int
    {
        return $this->disk()->lastModified($path);
    }

    public function makeDirectory(string $path): bool
    {
        return $this->disk()->makeDirectory($path);
    }

    public function deleteDirectory(string $path): bool
    {
        return $this->disk()->deleteDirectory($path);
    }

    protected function callCustomCreator(array $config): FilesystemDriverInterface
    {
        return $this->customCreators[$config['driver']]($config);
    }

    public function __call(string $method, array $parameters)
    {
        return $this->disk()->$method(...$parameters);
    }
}
