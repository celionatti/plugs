<?php

declare(strict_types=1);

namespace Plugs\Database;

use Plugs\Base\Model\PlugModel;

class Collection implements \ArrayAccess, \Iterator, \Countable, \JsonSerializable
{
    protected $items = [];
    protected $position = 0;

    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    // Iterator methods
    public function rewind(): void
    {
        $this->position = 0;
    }

    public function current(): mixed
    {
        return $this->items[$this->position];
    }

    public function key(): mixed
    {
        return $this->position;
    }

    public function next(): void
    {
        ++$this->position;
    }

    public function valid(): bool
    {
        return isset($this->items[$this->position]);
    }

    // ArrayAccess methods
    public function offsetExists($offset): bool
    {
        return isset($this->items[$offset]);
    }

    public function offsetGet($offset): mixed
    {
        return $this->items[$offset] ?? null;
    }

    public function offsetSet($offset, $value): void
    {
        if (is_null($offset)) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    public function offsetUnset($offset): void
    {
        unset($this->items[$offset]);
    }

    // Countable
    public function count(): int
    {
        return count($this->items);
    }

    // JsonSerializable
    public function jsonSerialize(): mixed
    {
        return array_map(function ($item) {
            return $item instanceof PlugModel ? $item->toArray() : $item;
        }, $this->items);
    }

    /**
     * Get all items
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Get first item
     */
    public function first()
    {
        return reset($this->items) ?: null;
    }

    /**
     * Get last item
     */
    public function last()
    {
        return end($this->items) ?: null;
    }

    /**
     * Check if collection is empty
     */
    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    /**
     * Check if collection is not empty
     */
    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    /**
     * Map over items
     */
    public function map(callable $callback): Collection
    {
        return new static(array_map($callback, $this->items));
    }

    /**
     * Filter items
     */
    public function filter(?callable $callback = null): Collection
    {
        if ($callback) {
            return new static(array_filter($this->items, $callback));
        }
        return new static(array_filter($this->items));
    }

    /**
     * Pluck values by key
     */
    public function pluck(string $key): Collection
    {
        return new static(array_map(function ($item) use ($key) {
            return $item instanceof PlugModel ? $item->$key : $item[$key] ?? null;
        }, $this->items));
    }

    /**
     * Get only specified keys
     */
    public function only(array $keys): Collection
    {
        return $this->map(function ($item) use ($keys) {
            if ($item instanceof PlugModel) {
                return array_intersect_key($item->toArray(), array_flip($keys));
            }
            return array_intersect_key($item, array_flip($keys));
        });
    }

    /**
     * Get unique items
     */
    public function unique(?string $key = null): Collection
    {
        if ($key) {
            $exists = [];
            return $this->filter(function ($item) use ($key, &$exists) {
                $value = $item instanceof PlugModel ? $item->$key : $item[$key] ?? null;
                if (in_array($value, $exists)) {
                    return false;
                }
                $exists[] = $value;
                return true;
            });
        }
        return new static(array_unique($this->items));
    }

    /**
     * Sort collection
     */
    public function sort(?callable $callback = null): Collection
    {
        $items = $this->items;
        if ($callback) {
            usort($items, $callback);
        } else {
            sort($items);
        }
        return new static($items);
    }

    /**
     * Sort by key
     */
    public function sortBy(string $key, bool $descending = false): Collection
    {
        $items = $this->items;
        usort($items, function ($a, $b) use ($key, $descending) {
            $aVal = $a instanceof PlugModel ? $a->$key : $a[$key] ?? null;
            $bVal = $b instanceof PlugModel ? $b->$key : $b[$key] ?? null;
            $result = $aVal <=> $bVal;
            return $descending ? -$result : $result;
        });
        return new static($items);
    }

    /**
     * Group by key
     */
    public function groupBy(string $key): Collection
    {
        $groups = [];
        foreach ($this->items as $item) {
            $value = $item instanceof PlugModel ? $item->$key : $item[$key] ?? null;
            $groups[$value][] = $item;
        }
        return new static(array_map(function ($group) {
            return new static($group);
        }, $groups));
    }

    /**
     * Chunk collection
     */
    public function chunk(int $size): Collection
    {
        $chunks = array_chunk($this->items, $size);
        return new static(array_map(function ($chunk) {
            return new static($chunk);
        }, $chunks));
    }

    /**
     * Take first n items
     */
    public function take(int $limit): Collection
    {
        return new static(array_slice($this->items, 0, $limit));
    }

    /**
     * Skip first n items
     */
    public function skip(int $offset): Collection
    {
        return new static(array_slice($this->items, $offset));
    }

    /**
     * Sum of values
     */
    public function sum(?string $key = null)
    {
        if ($key) {
            return array_sum($this->pluck($key)->all());
        }
        return array_sum($this->items);
    }

    /**
     * Average of values
     */
    public function avg(?string $key = null)
    {
        $count = $this->count();
        return $count > 0 ? $this->sum($key) / $count : 0;
    }

    /**
     * Find item by key-value
     */
    public function where(string $key, $value): Collection
    {
        return $this->filter(function ($item) use ($key, $value) {
            $itemValue = $item instanceof PlugModel ? $item->$key : $item[$key] ?? null;
            return $itemValue === $value;
        });
    }

    /**
     * Find first item by key-value
     */
    public function firstWhere(string $key, $value)
    {
        return $this->where($key, $value)->first();
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return array_map(function ($item) {
            return $item instanceof PlugModel ? $item->toArray() : $item;
        }, $this->items);
    }

    /**
     * Convert to JSON
     */
    public function toJson($options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }

    /**
     * Each - iterate with callback
     */
    public function each(callable $callback): Collection
    {
        foreach ($this->items as $key => $item) {
            if ($callback($item, $key) === false) {
                break;
            }
        }
        return $this;
    }

    /**
     * Contains - check if item exists
     */
    public function contains($key, $value = null): bool
    {
        if (func_num_args() === 1) {
            return in_array($key, $this->items);
        }
        return $this->firstWhere($key, $value) !== null;
    }
}
