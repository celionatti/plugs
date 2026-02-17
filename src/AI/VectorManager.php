<?php

declare(strict_types=1);

namespace Plugs\AI;

use InvalidArgumentException;
use Plugs\AI\Contracts\VectorStore;
use Plugs\AI\Drivers\Vector\LocalVectorStore;

class VectorManager
{
    protected array $stores = [];
    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Get a vector store instance.
     */
    public function store(?string $name = null): VectorStore
    {
        $name = $name ?: $this->getDefaultDriver();

        if (!isset($this->stores[$name])) {
            $this->stores[$name] = $this->createStore($name);
        }

        return $this->stores[$name];
    }

    /**
     * Create a new store instance.
     */
    protected function createStore(string $name): VectorStore
    {
        $config = $this->config['stores'][$name] ?? null;

        if (!$config) {
            throw new InvalidArgumentException("Vector store [{$name}] is not configured.");
        }

        $method = 'create' . ucfirst($name) . 'Store';

        if (method_exists($this, $method)) {
            return $this->$method($config);
        }

        throw new InvalidArgumentException("Vector store [{$name}] is not supported.");
    }

    protected function createLocalStore(array $config): LocalVectorStore
    {
        return new LocalVectorStore($config);
    }

    public function getDefaultDriver(): string
    {
        return $this->config['default'] ?? 'local';
    }

    /**
     * Search for similar vectors. Accepts a string or a vector.
     */
    public function search(string|array $query, int $limit = 10): array
    {
        if (is_string($query)) {
            $query = \Plugs\Facades\AI::embed($query);
        }

        return $this->store()->search($query, $limit);
    }

    /**
     * Dynamically call the default store instance.
     */
    public function __call(string $method, array $parameters)
    {
        return $this->store()->$method(...$parameters);
    }
}
