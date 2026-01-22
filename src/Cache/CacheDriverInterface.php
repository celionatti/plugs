<?php

declare(strict_types=1);

namespace Plugs\Cache;

interface CacheDriverInterface
{
    public function get(string $key, $default = null);

    public function set(string $key, $value, int|null $ttl = null): bool;

    public function delete(string $key): bool;

    public function clear(): bool;

    public function has(string $key): bool;

    public function getMultiple(iterable $keys, $default = null): iterable;

    public function setMultiple(iterable $values, int|null $ttl = null): bool;

    public function deleteMultiple(iterable $keys): bool;
}
