<?php

declare(strict_types=1);

namespace Plugs\View;

use Countable;

class Loop
{
    /**
     * The current loop index (0-based).
     */
    public int $index = 0;

    /**
     * The current loop iteration (1-based).
     */
    public int $iteration = 1;

    /**
     * The total number of items in the loop.
     */
    public int $count = 0;

    /**
     * The depth of the loop nesting.
     */
    public int $depth;

    /**
     * The parent loop variable.
     */
    public ?Loop $parent;

    /**
     * Create a new Loop instance.
     *
     * @param int|Countable|array $count The total count of items or the iterable itself
     * @param Loop|null $parent The parent loop
     * @param int $depth The nesting depth
     */
    public function __construct($count, ?Loop $parent = null, int $depth = 1)
    {
        $this->count = is_countable($count) ? count($count) : (is_array($count) ? count($count) : (int) $count);
        $this->parent = $parent;
        $this->depth = $depth;
    }

    /**
     * Tick the loop forward one iteration.
     */
    public function tick(): void
    {
        $this->index++;
        $this->iteration++;
    }

    /**
     * Check if this is the first iteration.
     */
    public function first(): bool
    {
        return $this->index === 0;
    }

    /**
     * Check if this is the last iteration.
     */
    public function last(): bool
    {
        return $this->index === $this->count - 1;
    }

    /**
     * Check if this is an even iteration (based on index).
     */
    public function even(): bool
    {
        return $this->iteration % 2 === 0;
    }

    /**
     * Check if this is an odd iteration (based on index).
     */
    public function odd(): bool
    {
        return $this->iteration % 2 !== 0;
    }

    /**
     * Get the remaining number of iterations.
     */
    public function remaining(): int
    {
        return $this->count - $this->iteration;
    }
}
