<?php

declare(strict_types=1);

namespace Plugs\Database\Factory;

/**
 * SequenceGenerator
 *
 * Generates cyclic or sequential values from a set of data.
 * Useful for ensuring variety in seeded data.
 *
 * @package Plugs\Database\Factory
 */
class SequenceGenerator
{
    protected array $sequence;
    protected int $index = 0;

    /**
     * Create a new sequence generator
     */
    public function __construct(array $sequence)
    {
        $this->sequence = $sequence;
    }

    /**
     * Get the next value in the sequence
     */
    public function next(): mixed
    {
        if (empty($this->sequence)) {
            return null;
        }

        $value = $this->sequence[$this->index];

        $this->index = ($this->index + 1) % count($this->sequence);

        return $value;
    }

    /**
     * Reset the sequence to the beginning
     */
    public function reset(): void
    {
        $this->index = 0;
    }

    /**
     * Get the total count of items in sequence
     */
    public function count(): int
    {
        return count($this->sequence);
    }
}
