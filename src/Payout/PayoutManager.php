<?php

declare(strict_types=1);

namespace Plugs\Payout;

use InvalidArgumentException;
use Plugs\Payout\Contracts\PayoutDriverInterface;

class PayoutManager
{
    /**
     * The array of created "drivers".
     *
     * @var array
     */
    protected array $drivers = [];

    /**
     * The configuration array or object.
     *
     * @var mixed
     */
    protected $config;

    /**
     * The registered custom driver creators.
     *
     * @var array
     */
    protected array $customCreators = [];

    /**
     * The registered smart routing rules.
     *
     * @var array
     */
    protected array $routeRules = [];

    /**
     * Create a new PayoutManager instance.
     *
     * @param mixed $config
     */
    public function __construct($config = [])
    {
        $this->config = $config;
    }

    /**
     * Register a new smart routing rule.
     *
     * @param callable $rule
     * @return $this
     */
    public function addRouteRule(callable $rule): self
    {
        $this->routeRules[] = $rule;
        return $this;
    }

    /**
     * Resolve the best gateway based on the payload and registered rules.
     *
     * @param array $payload
     * @return PayoutDriverInterface
     */
    public function smart(array $payload = []): PayoutDriverInterface
    {
        foreach ($this->routeRules as $rule) {
            $gateway = $rule($payload);
            if ($gateway !== null) {
                return $this->driver($gateway);
            }
        }

        return $this->driver(); // fallback to default
    }

    /**
     * Get a payout driver instance, or a Fallback driver if an array is passed.
     *
     * @param string|array|null $driver
     * @return PayoutDriverInterface
     */
    public function driver($driver = null): PayoutDriverInterface
    {
        $driver = $driver ?: $this->getDefaultDriver();

        if ($driver === null) {
            throw new InvalidArgumentException(sprintf(
                'Unable to resolve NULL driver for [%s].',
                static::class
            ));
        }

        // Handle Automatic Gateway Fallback
        if (is_array($driver)) {
            return $this->fallback($driver);
        }

        if (!isset($this->drivers[$driver])) {
            $this->drivers[$driver] = $this->createDriver($driver);
        }

        return $this->drivers[$driver];
    }

    /**
     * Create a Fallback sequence of payout drivers.
     *
     * @param array $drivers
     * @return PayoutDriverInterface
     */
    public function fallback(array $drivers): PayoutDriverInterface
    {
        return current($drivers); // TODO: implement full FallbackPayoutDriver if requested by user, stubbed for now
    }

    /**
     * Create a new driver instance.
     *
     * @param string $driver
     * @return PayoutDriverInterface
     *
     * @throws InvalidArgumentException
     */
    protected function createDriver(string $driver): PayoutDriverInterface
    {
        if (isset($this->customCreators[$driver])) {
            return $this->callCustomCreator($driver);
        }

        $method = 'create' . ucfirst(str_replace('-', '', $driver)) . 'Driver';

        if (method_exists($this, $method)) {
            return $this->$method();
        }

        throw new InvalidArgumentException("Driver [$driver] not supported.");
    }

    /**
     * Call a custom driver creator.
     *
     * @param string $driver
     * @return PayoutDriverInterface
     */
    protected function callCustomCreator(string $driver): PayoutDriverInterface
    {
        return $this->customCreators[$driver]($this->getConfig($driver));
    }

    /**
     * Register a custom driver creator Closure.
     *
     * @param string $driver
     * @param \Closure $callback
     * @return $this
     */
    public function extend(string $driver, \Closure $callback): self
    {
        $this->customCreators[$driver] = $callback;
        return $this;
    }

    /**
     * Get the payout configuration.
     *
     * @param string $name
     * @return array
     */
    protected function getConfig(string $name): array
    {
        if (is_array($this->config)) {
            return $this->config['drivers'][$name] ?? [];
        }

        if (is_object($this->config) && method_exists($this->config, 'get')) {
            return $this->config->get("payouts.drivers.{$name}", []);
        }

        return [];
    }

    /**
     * Get the default payout driver name.
     *
     * @return string|null
     */
    public function getDefaultDriver(): ?string
    {
        if (is_array($this->config)) {
            return $this->config['default'] ?? null;
        }

        if (is_object($this->config) && method_exists($this->config, 'get')) {
            return $this->config->get('payouts.default');
        }

        return null;
    }

    /**
     * Dynamically call the default driver instance.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->driver()->$method(...$parameters);
    }
}
