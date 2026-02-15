<?php

declare(strict_types=1);

namespace Plugs\View;

use InvalidArgumentException;
use Plugs\Container\Container;

/**
 * Class ViewManager
 *
 * Manages different view engine drivers for the Plugs framework.
 *
 * @package Plugs\View
 */
class ViewManager
{
    /**
     * The container instance.
     */
    protected Container $container;

    /**
     * The array of resolved view engines.
     */
    protected array $drivers = [];

    /**
     * The custom driver creators.
     */
    protected array $customCreators = [];

    /**
     * Create a new ViewManager instance.
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Get a view engine driver instance.
     *
     * @param string|null $driver
     * @return ViewEngineInterface
     */
    public function driver(?string $driver = null): ViewEngineInterface
    {
        $driver = $driver ?: $this->getDefaultDriver();

        if (!isset($this->drivers[$driver])) {
            $this->drivers[$driver] = $this->createDriver($driver);
        }

        return $this->drivers[$driver];
    }

    /**
     * Create a new driver instance.
     *
     * @param string $driver
     * @return ViewEngineInterface
     * @throws InvalidArgumentException
     */
    protected function createDriver(string $driver): ViewEngineInterface
    {
        if (isset($this->customCreators[$driver])) {
            return $this->callCustomCreator($driver);
        }

        $method = 'create' . ucfirst($driver) . 'Driver';

        if (method_exists($this, $method)) {
            return $this->$method();
        }

        throw new InvalidArgumentException("Driver [{$driver}] not supported.");
    }

    /**
     * Call a custom driver creator.
     *
     * @param string $driver
     * @return ViewEngineInterface
     */
    protected function callCustomCreator(string $driver): ViewEngineInterface
    {
        return $this->customCreators[$driver]($this->container);
    }

    /**
     * Create an instance of the Plug view driver.
     *
     * @return PlugViewEngine
     */
    protected function createPlugDriver(): PlugViewEngine
    {
        $config = config('app.paths');

        $engine = new PlugViewEngine(
            $config['views'],
            $config['cache'],
            $this->container,
            config('app.env') === 'production'
        );

        $engine->setOpcacheEnabled(config('opcache.enabled', true));

        return $engine;
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
     * Get the default view driver name.
     *
     * @return string
     */
    public function getDefaultDriver(): string
    {
        return config('view.driver', 'plug');
    }

    /**
     * Dynamically call the default driver instance.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        return $this->driver()->$method(...$parameters);
    }
}
