<?php

declare(strict_types=1);

namespace Plugs\Payment;

use InvalidArgumentException;
use Plugs\Payment\Contracts\PaymentDriverInterface;

class PaymentManager
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
     * Create a new PaymentManager instance.
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
     * @return PaymentDriverInterface
     */
    public function smart(array $payload = []): PaymentDriverInterface
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
     * Get a payment driver instance, or a Fallback driver if an array is passed.
     *
     * @param string|array|null $driver
     * @return PaymentDriverInterface
     */
    public function driver($driver = null): PaymentDriverInterface
    {
        $driver = $driver ?: $this->getDefaultDriver();

        if ($driver === null) {
            throw new InvalidArgumentException(sprintf(
                'Unable to resolve NULL driver for [%s].',
                static::class
            ));
        }

        // Handle Automatic Gatway Fallback
        if (is_array($driver)) {
            return $this->fallback($driver);
        }

        if (!isset($this->drivers[$driver])) {
            $this->drivers[$driver] = $this->createDriver($driver);
        }

        return $this->drivers[$driver];
    }

    /**
     * Create a Fallback sequence of payment drivers.
     *
     * @param array $drivers
     * @return PaymentDriverInterface
     */
    public function fallback(array $drivers): PaymentDriverInterface
    {
        return new \Plugs\Payment\Drivers\FallbackPaymentDriver($this, $drivers);
    }

    /**
     * Create a new driver instance.
     *
     * @param string $driver
     * @return PaymentDriverInterface
     *
     * @throws InvalidArgumentException
     */
    protected function createDriver(string $driver): PaymentDriverInterface
    {
        // First, we will determine if a custom driver creator exists for the given driver and
        // if it does not we will check for a creator method for the driver. Custom
        // creator callbacks allow developers to build their own "drivers" easily.
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
     * Create an instance of the Paystack driver.
     *
     * @return \Plugs\Payment\Drivers\PaystackPaymentDriver
     */
    protected function createPaystackDriver(): \Plugs\Payment\Drivers\PaystackPaymentDriver
    {
        return new \Plugs\Payment\Drivers\PaystackPaymentDriver($this->getConfig('paystack'));
    }

    /**
     * Create an instance of the Stripe driver.
     *
     * @return \Plugs\Payment\Drivers\StripePaymentDriver
     */
    protected function createStripeDriver(): \Plugs\Payment\Drivers\StripePaymentDriver
    {
        return new \Plugs\Payment\Drivers\StripePaymentDriver($this->getConfig('stripe'));
    }

    /**
     * Create an instance of the Flutterwave driver.
     *
     * @return \Plugs\Payment\Drivers\FlutterwavePaymentDriver
     */
    protected function createFlutterwaveDriver(): \Plugs\Payment\Drivers\FlutterwavePaymentDriver
    {
        return new \Plugs\Payment\Drivers\FlutterwavePaymentDriver($this->getConfig('flutterwave'));
    }

    /**
     * Create an instance of the PayPal driver.
     *
     * @return \Plugs\Payment\Drivers\PayPalPaymentDriver
     */
    protected function createPaypalDriver(): \Plugs\Payment\Drivers\PayPalPaymentDriver
    {
        return new \Plugs\Payment\Drivers\PayPalPaymentDriver($this->getConfig('paypal'));
    }

    /**
     * Create an instance of the Payoneer driver.
     *
     * @return \Plugs\Payment\Drivers\PayoneerPaymentDriver
     */
    protected function createPayoneerDriver(): \Plugs\Payment\Drivers\PayoneerPaymentDriver
    {
        return new \Plugs\Payment\Drivers\PayoneerPaymentDriver($this->getConfig('payoneer'));
    }

    /**
     * Create an instance of the BTCPay driver.
     *
     * @return \Plugs\Payment\Drivers\BTCPayPaymentDriver
     */
    protected function createBtcpayDriver(): \Plugs\Payment\Drivers\BTCPayPaymentDriver
    {
        return new \Plugs\Payment\Drivers\BTCPayPaymentDriver($this->getConfig('btcpay'));
    }

    /**
     * Call a custom driver creator.
     *
     * @param string $driver
     * @return PaymentDriverInterface
     */
    protected function callCustomCreator(string $driver): PaymentDriverInterface
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
     * Get the payment configuration.
     *
     * @param string $name
     * @return array
     */
    protected function getConfig(string $name): array
    {
        if (is_array($this->config)) {
            return $this->config['drivers'][$name] ?? [];
        }

        // Assuming a standard config repository object that has a `get()` method
        if (is_object($this->config) && method_exists($this->config, 'get')) {
            return $this->config->get("payments.drivers.{$name}", []);
        }

        return [];
    }

    /**
     * Get the default payment driver name.
     *
     * @return string|null
     */
    public function getDefaultDriver(): ?string
    {
        if (is_array($this->config)) {
            return $this->config['default'] ?? null;
        }

        if (is_object($this->config) && method_exists($this->config, 'get')) {
            return $this->config->get('payments.default');
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
