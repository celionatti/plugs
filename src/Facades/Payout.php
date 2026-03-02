<?php

declare(strict_types=1);

namespace Plugs\Facades;

use Plugs\Payout\Contracts\PayoutDriverInterface;

/**
 * @method static PayoutDriverInterface driver(string|array $driver = null)
 * @method static PayoutDriverInterface fallback(array $drivers)
 * @method static PayoutDriverInterface smart(array $payload = [])
 * @method static \Plugs\Payout\PayoutManager addRouteRule(callable $rule)
 * @method static \Plugs\Payout\DTO\TransferResponse transfer(array $payload)
 * @method static \Plugs\Payout\DTO\WithdrawResponse withdraw(array $payload)
 * @method static \Plugs\Payout\DTO\PayoutVerification verify(string $reference)
 * @method static array getBalance()
 * @method static array createRecipient(array $data)
 * @method static \Plugs\Payout\PayoutManager extend(string $driver, \Closure $callback)
 *
 * @see \Plugs\Payout\PayoutManager
 */
class Payout
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'payout';
    }

    /**
     * Handle dynamic, static calls to the object.
     *
     * @param string $method
     * @param array $args
     * @return mixed
     *
     * @throws \RuntimeException
     */
    public static function __callStatic($method, $args)
    {
        $instance = static::resolveFacadeInstance(static::getFacadeAccessor());

        if (!$instance) {
            throw new \RuntimeException('A facade root has not been set.');
        }

        return $instance->$method(...$args);
    }

    /**
     * Resolve the facade root instance from the container.
     *
     * @param string|object $name
     * @return mixed
     */
    protected static function resolveFacadeInstance($name)
    {
        // This expects the application container to have bound the 'payout' key to a PayoutManager instance.
        if (class_exists('\Plugs\Container\Container')) {
            // Retrieve instance from application container if using the main framework
            return \Plugs\Container\Container::getInstance()->make($name);
        }

        return null;
    }
}
