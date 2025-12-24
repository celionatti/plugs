<?php

declare(strict_types=1);

namespace Plugs\Inertia;

/*
|--------------------------------------------------------------------------
| LazyProp Class
|--------------------------------------------------------------------------
|
| Represents a lazy-loaded prop that is only evaluated when specifically
| requested by the client. This is useful for expensive computations
| that shouldn't run on every request.
*/

class LazyProp
{
    /**
     * The callback to evaluate
     */
    private \Closure $callback;

    /**
     * Create a new lazy prop instance
     *
     * @param callable $callback The callback to evaluate lazily
     */
    public function __construct(callable $callback)
    {
        $this->callback = \Closure::fromCallable($callback);
    }

    /**
     * Evaluate the lazy prop
     *
     * @return mixed The result of the callback
     */
    public function __invoke(): mixed
    {
        return ($this->callback)();
    }
}
