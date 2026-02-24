<?php

declare(strict_types=1);

namespace Plugs\AI;

use Plugs\Support\LazyString;

/**
 * A proxy for AIManager that wraps all calls in LazyString.
 */
class DeferredAIManager
{
    protected AIManager $manager;

    public function __construct(AIManager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * Defer a prompt.
     */
    public function prompt(string $template, array $data = [], array $options = []): LazyString
    {
        return new LazyString(function () use ($template, $data, $options) {
            return $this->manager->prompt($template, $data, $options);
        });
    }

    /**
     * Defer a classification.
     */
    public function classify(string $text, array $categories = [], array $options = []): LazyString
    {
        return new LazyString(function () use ($text, $categories, $options) {
            return $this->manager->classify($text, $categories, $options);
        });
    }

    /**
     * Pass everything else to the manager.
     */
    public function __call(string $method, array $parameters)
    {
        $asyncMethod = $method . 'Async';
        $driver = $this->manager->driver();

        if (method_exists($driver, $asyncMethod)) {
            return new LazyString(function () use ($asyncMethod, $parameters, $driver) {
                $result = $driver->$asyncMethod(...$parameters);

                // If it's a promise, wait for it
                if (is_object($result) && method_exists($result, 'wait')) {
                    return $result->wait();
                }

                // If it's a closure, call it
                if ($result instanceof \Closure) {
                    return $result();
                }

                return (string) $result;
            });
        }

        return $this->manager->$method(...$parameters);
    }
}
