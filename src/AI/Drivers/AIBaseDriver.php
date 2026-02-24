<?php

declare(strict_types=1);

namespace Plugs\AI\Drivers;

use Plugs\AI\Contracts\AIDriverInterface;

abstract class AIBaseDriver implements AIDriverInterface
{
    protected string $model;
    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->model = $config['model'] ?? '';
    }

    /**
     * @inheritDoc
     */
    public function withModel(string $model): self
    {
        $this->model = $model;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function promptAsync(string $prompt, array $options = [])
    {
        return fn() => $this->prompt($prompt, $options);
    }

    /**
     * @inheritDoc
     */
    public function chatAsync(array $messages, array $options = [])
    {
        return fn() => $this->chat($messages, $options);
    }


    /**
     * Get a config value.
     */
    protected function getConfig(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * @inheritDoc
     */
    public function embed(string $text): array
    {
        throw new \RuntimeException(sprintf('Embedding is not supported by the [%s] driver.', class_basename(static::class)));
    }
}
