<?php

declare(strict_types=1);

namespace Plugs\AI;

use InvalidArgumentException;
use Plugs\AI\Contracts\AIDriverInterface;
use Plugs\AI\Drivers\OpenAIDriver;
use Plugs\AI\Drivers\AnthropicDriver;
use Plugs\AI\Drivers\GeminiDriver;
use Plugs\AI\Drivers\GroqDriver;
use Plugs\AI\Drivers\OpenRouterDriver;

class AIManager
{
    protected array $drivers = [];
    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Get a driver instance.
     */
    public function driver(?string $name = null): AIDriverInterface
    {
        $name = $name ?: $this->guessDriver();

        if (!isset($this->drivers[$name])) {
            $this->drivers[$name] = $this->createDriver($name);
        }

        return $this->drivers[$name];
    }

    /**
     * Create a new driver instance.
     */
    protected function createDriver(string $name): AIDriverInterface
    {
        $config = $this->config['providers'][$name] ?? null;

        if (!$config) {
            throw new InvalidArgumentException("Driver [{$name}] is not configured.");
        }

        $method = 'create' . ucfirst($name) . 'Driver';

        if (method_exists($this, $method)) {
            return $this->$method($config);
        }

        throw new InvalidArgumentException("Driver [{$name}] is not supported.");
    }

    protected function createOpenaiDriver(array $config): OpenAIDriver
    {
        return new OpenAIDriver($config);
    }

    protected function createAnthropicDriver(array $config): AnthropicDriver
    {
        return new AnthropicDriver($config);
    }

    protected function createGeminiDriver(array $config): GeminiDriver
    {
        return new GeminiDriver($config);
    }

    protected function createGroqDriver(array $config): GroqDriver
    {
        return new GroqDriver($config);
    }

    protected function createOpenrouterDriver(array $config): OpenRouterDriver
    {
        return new OpenRouterDriver($config);
    }

    public function getDefaultDriver(): string
    {
        return $this->config['default'] ?? 'openai';
    }

    /**
     * Guess the best driver based on configured keys.
     */
    protected function guessDriver(): string
    {
        $default = $this->getDefaultDriver();
        $providers = $this->config['providers'] ?? [];

        // If the default driver has a key, use it
        if (!empty($providers[$default]['api_key'])) {
            return $default;
        }

        // Otherwise, find the first driver that has a key
        foreach ($providers as $name => $config) {
            if (!empty($config['api_key'])) {
                return $name;
            }
        }

        return $default;
    }

    /**
     * Dynamically call the default driver instance.
     */
    public function __call(string $method, array $parameters)
    {
        return $this->driver()->$method(...$parameters);
    }
}
