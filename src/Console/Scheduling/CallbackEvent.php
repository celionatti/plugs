<?php

declare(strict_types=1);

namespace Plugs\Console\Scheduling;

/**
 * Represents a scheduled callback event.
 */
class CallbackEvent extends Event
{
    protected $callback;
    protected array $callbackParameters;

    public function __construct(callable $callback, array $parameters = [])
    {
        parent::__construct('Callback', []);
        $this->callback = $callback;
        $this->callbackParameters = $parameters;
    }

    /**
     * Get the callback.
     */
    public function getCallback(): callable
    {
        return $this->callback;
    }

    /**
     * Get the callback parameters.
     */
    public function getCallbackParameters(): array
    {
        return $this->callbackParameters;
    }

    /**
     * Run the callback.
     *
     * @return mixed
     */
    public function run(): mixed
    {
        return call_user_func_array($this->callback, $this->callbackParameters);
    }
}
