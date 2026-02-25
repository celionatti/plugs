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
     * Create a new Loop instance.
     *
     * @param mixed $items The total count of items or the iterable itself
     * @param Loop|null $parent The parent loop
     * @param int $depth The nesting depth
     */
    public function __construct(mixed $items, ?Loop $parent = null, int $depth = 1)
    {
        if (is_array($items)) {
            $this->count = count($items);
        } elseif ($items instanceof Countable) {
            $this->count = count($items);
        } elseif (is_int($items)) {
            $this->count = $items;
        } else {
            // For Generators or other iterables where count isn't immediately known
            $this->count = -1;
        }

        $this->parent = $parent;
        $this->depth = $depth;
    }

    /**
     * The parent loop variable.
     */
    public ?Loop $parent;

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
        if ($this->count === -1) {
            return false;
        }

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
    public function remaining(): ?int
    {
        if ($this->count === -1) {
            return null;
        }

        return $this->count - $this->iteration;
    }

    /**
     * Get the total number of items in the loop.
     */
    public function total(): int
    {
        return $this->count;
    }

    /**
     * Check if the loop should trigger an automatic buffer flush.
     * Helpful for large datasets to keep memory low and TTFB fast.
     *
     * @param int $frequency
     * @return bool
     */
    public function shouldFlush(int $frequency = 50): bool
    {
        return $this->index > 0 && $this->index % $frequency === 0;
    }
}
