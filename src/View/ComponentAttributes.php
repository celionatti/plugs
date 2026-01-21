<?php

declare(strict_types=1);

namespace Plugs\View;

use ArrayAccess;
use IteratorAggregate;
use Countable;
use ArrayIterator;

class ComponentAttributes implements ArrayAccess, IteratorAggregate, Countable
{
    protected array $attributes = [];

    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    /**
     * Get an attribute.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /**
     * Check if an attribute exists.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->attributes);
    }

    /**
     * Get all attributes as an array.
     */
    public function all(): array
    {
        return $this->attributes;
    }

    /**
     * Get attributes except the given keys.
     */
    public function except(string|array $keys): static
    {
        $keys = (array) $keys;
        $attributes = $this->attributes;

        foreach ($keys as $key) {
            unset($attributes[$key]);
        }

        return new static($attributes);
    }

    /**
     * Get attributes only for the given keys.
     */
    public function only(string|array $keys): static
    {
        $keys = (array) $keys;
        $result = [];

        foreach ($keys as $key) {
            if (array_key_exists($key, $this->attributes)) {
                $result[$key] = $this->attributes[$key];
            }
        }

        return new static($result);
    }

    /**
     * Merge additional attributes / defaults.
     * Existing attributes take precedence, except for 'class' which is appended.
     */
    public function merge(array $attributes): static
    {
        $merged = $this->attributes;

        foreach ($attributes as $key => $value) {
            if ($key === 'class') {
                $existing = $merged[$key] ?? '';
                $merged[$key] = trim($existing . ' ' . $value);
            } elseif (!array_key_exists($key, $merged)) {
                $merged[$key] = $value;
            }
        }

        return new static($merged);
    }

    /**
     * Check if the attribute bag is empty.
     */
    public function isEmpty(): bool
    {
        return empty($this->attributes);
    }

    /**
     * Check if the attribute bag is not empty.
     */
    public function isNotEmpty(): bool
    {
        return !empty($this->attributes);
    }

    /**
     * Get the string representation of the attributes.
     */
    public function __toString(): string
    {
        $html = [];

        foreach ($this->attributes as $key => $value) {
            if ($value === true) {
                $html[] = $key;
            } elseif ($value !== false && $value !== null) {
                $html[] = sprintf('%s="%s"', $key, htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'));
            }
        }

        return implode(' ', $html);
    }

    // ArrayAccess Implementation
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->attributes[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->get($offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->attributes[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->attributes[$offset]);
    }

    // IteratorAggregate Implementation
    public function getIterator(): \Traversable
    {
        return new ArrayIterator($this->attributes);
    }

    // Countable Implementation
    public function count(): int
    {
        return count($this->attributes);
    }
}