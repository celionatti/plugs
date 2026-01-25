<?php

declare(strict_types=1);

namespace Plugs\Database;

use Plugs\Base\Model\PlugModel;

class Collection implements \ArrayAccess, \Iterator, \Countable, \JsonSerializable
{
    protected $items = [];
    protected $position = 0;

    protected $_pivotModel = null;
    protected $_pivotRelation = null;
    protected $_pivotConfig = null;

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
    public function first(?callable $callback = null, $default = null)
    {
        if (is_null($callback)) {
            return reset($this->items) ?: $default;
        }

        foreach ($this->items as $key => $item) {
            if ($callback($item, $key)) {
                return $item;
            }
        }

        return $default;
    }

    /**
     * Get last item
     */
    public function last(?callable $callback = null, $default = null)
    {
        if (is_null($callback)) {
            return end($this->items) ?: $default;
        }

        return $this->reverse()->first($callback, $default);
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
        $keys = array_keys($this->items);
        $items = array_map($callback, $this->items, $keys);

        return new static(array_combine($keys, $items));
    }

    /**
     * Filter items
     */
    public function filter(?callable $callback = null): Collection
    {
        if ($callback) {
            return new static(array_filter($this->items, $callback, ARRAY_FILTER_USE_BOTH));
        }

        return new static(array_filter($this->items));
    }

    /**
     * Reject items (opposite of filter)
     */
    public function reject(callable $callback): Collection
    {
        return $this->filter(function ($item, $key) use ($callback) {
            return !$callback($item, $key);
        });
    }

    /**
     * Pluck values by key
     */
    public function pluck(string $key, ?string $keyBy = null): Collection
    {
        $results = [];

        foreach ($this->items as $item) {
            $value = $item instanceof PlugModel ? $item->$key : ($item[$key] ?? null);

            if ($keyBy !== null) {
                $itemKey = $item instanceof PlugModel ? $item->$keyBy : ($item[$keyBy] ?? null);
                $results[$itemKey] = $value;
            } else {
                $results[] = $value;
            }
        }

        return new static($results);
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
     * Get except specified keys
     */
    public function except(array $keys): Collection
    {
        return $this->map(function ($item) use ($keys) {
            if ($item instanceof PlugModel) {
                return array_diff_key($item->toArray(), array_flip($keys));
            }

            return array_diff_key($item, array_flip($keys));
        });
    }

    /**
     * Get unique items
     */
    public function unique(?string $key = null, bool $strict = false): Collection
    {
        if ($key) {
            $exists = [];

            return $this->filter(function ($item) use ($key, &$exists, $strict) {
                $value = $item instanceof PlugModel ? $item->$key : ($item[$key] ?? null);

                if ($strict) {
                    if (in_array($value, $exists, true)) {
                        return false;
                    }
                } else {
                    if (in_array($value, $exists)) {
                        return false;
                    }
                }

                $exists[] = $value;

                return true;
            });
        }

        return new static(array_unique($this->items, SORT_REGULAR));
    }

    /**
     * Get duplicate items
     */
    public function duplicates(?string $key = null): Collection
    {
        $seen = [];
        $duplicates = [];

        foreach ($this->items as $item) {
            $value = $key ? ($item instanceof PlugModel ? $item->$key : ($item[$key] ?? null)) : $item;

            if (in_array($value, $seen)) {
                $duplicates[] = $item;
            } else {
                $seen[] = $value;
            }
        }

        return new static($duplicates);
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
            $aVal = $a instanceof PlugModel ? $a->$key : ($a[$key] ?? null);
            $bVal = $b instanceof PlugModel ? $b->$key : ($b[$key] ?? null);
            $result = $aVal <=> $bVal;

            return $descending ? -$result : $result;
        });

        return new static($items);
    }

    /**
     * Sort in descending order by key
     */
    public function sortByDesc(string $key): Collection
    {
        return $this->sortBy($key, true);
    }

    /**
     * Reverse the collection
     */
    public function reverse(): Collection
    {
        return new static(array_reverse($this->items, true));
    }

    /**
     * Shuffle items randomly
     */
    public function shuffle(): Collection
    {
        $items = $this->items;
        shuffle($items);

        return new static($items);
    }

    /**
     * Group by key
     */
    public function groupBy(string $key): Collection
    {
        $groups = [];
        foreach ($this->items as $item) {
            $value = $item instanceof PlugModel ? $item->$key : ($item[$key] ?? null);
            $groups[$value][] = $item;
        }

        return new static(array_map(function ($group) {
            return new static($group);
        }, $groups));
    }

    /**
     * Key collection by a given key
     */
    public function keyBy(string $key): Collection
    {
        $results = [];
        foreach ($this->items as $item) {
            $keyValue = $item instanceof PlugModel ? $item->$key : ($item[$key] ?? null);
            $results[$keyValue] = $item;
        }

        return new static($results);
    }

    /**
     * Chunk collection
     */
    public function chunk(int $size): Collection
    {
        $chunks = array_chunk($this->items, $size, true);

        return new static(array_map(function ($chunk) {
            return new static($chunk);
        }, $chunks));
    }

    /**
     * Split collection into groups
     */
    public function split(int $numberOfGroups): Collection
    {
        if ($this->isEmpty()) {
            return new static();
        }

        $groupSize = ceil($this->count() / $numberOfGroups);

        return $this->chunk((int) $groupSize);
    }

    /**
     * Take first n items
     */
    public function take(int $limit): Collection
    {
        if ($limit < 0) {
            return $this->slice($limit, abs($limit));
        }

        return $this->slice(0, $limit);
    }

    /**
     * Skip first n items
     */
    public function skip(int $offset): Collection
    {
        return $this->slice($offset);
    }

    /**
     * Slice the collection
     */
    public function slice(int $offset, ?int $length = null): Collection
    {
        return new static(array_slice($this->items, $offset, $length, true));
    }

    /**
     * Get nth item(s)
     */
    public function nth(int $step, int $offset = 0): Collection
    {
        $new = [];
        $position = 0;

        foreach ($this->items as $item) {
            if ($position % $step === $offset) {
                $new[] = $item;
            }
            $position++;
        }

        return new static($new);
    }

    /**
     * Concatenate values
     */
    public function implode(string $glue, ?string $key = null): string
    {
        if ($key === null) {
            return implode($glue, $this->items);
        }

        return implode($glue, $this->pluck($key)->all());
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
     * Median value
     */
    public function median(?string $key = null)
    {
        $values = $key ? $this->pluck($key)->all() : $this->items;
        sort($values);
        $count = count($values);

        if ($count === 0) {
            return 0;
        }

        $middle = (int) ($count / 2);

        if ($count % 2) {
            return $values[$middle];
        }

        return ($values[$middle - 1] + $values[$middle]) / 2;
    }

    /**
     * Mode value(s)
     */
    public function mode(?string $key = null)
    {
        $values = $key ? $this->pluck($key)->all() : $this->items;

        if (empty($values)) {
            return null;
        }

        $counts = array_count_values($values);
        $maxCount = max($counts);

        return array_keys($counts, $maxCount);
    }

    /**
     * Maximum value
     */
    public function max(?string $key = null)
    {
        $values = $key ? $this->pluck($key)->all() : $this->items;

        return !empty($values) ? max($values) : null;
    }

    /**
     * Minimum value
     */
    public function min(?string $key = null)
    {
        $values = $key ? $this->pluck($key)->all() : $this->items;

        return !empty($values) ? min($values) : null;
    }

    /**
     * Find item by key-value
     */
    public function where(string $key, $operator = null, $value = null): Collection
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        return $this->filter(function ($item) use ($key, $operator, $value) {
            $itemValue = $item instanceof PlugModel ? $item->$key : ($item[$key] ?? null);

            switch ($operator) {
                case '=':
                case '==':
                    return $itemValue == $value;
                case '===':
                    return $itemValue === $value;
                case '!=':
                case '<>':
                    return $itemValue != $value;
                case '!==':
                    return $itemValue !== $value;
                case '>':
                    return $itemValue > $value;
                case '>=':
                    return $itemValue >= $value;
                case '<':
                    return $itemValue < $value;
                case '<=':
                    return $itemValue <= $value;
                default:
                    return $itemValue == $value;
            }
        });
    }

    /**
     * Find items where key is in array
     */
    public function whereIn(string $key, array $values): Collection
    {
        return $this->filter(function ($item) use ($key, $values) {
            $itemValue = $item instanceof PlugModel ? $item->$key : ($item[$key] ?? null);

            return in_array($itemValue, $values);
        });
    }

    /**
     * Find items where key is not in array
     */
    public function whereNotIn(string $key, array $values): Collection
    {
        return $this->filter(function ($item) use ($key, $values) {
            $itemValue = $item instanceof PlugModel ? $item->$key : ($item[$key] ?? null);

            return !in_array($itemValue, $values);
        });
    }

    /**
     * Find items where key is null
     */
    public function whereNull(string $key): Collection
    {
        return $this->filter(function ($item) use ($key) {
            $itemValue = $item instanceof PlugModel ? $item->$key : ($item[$key] ?? null);

            return is_null($itemValue);
        });
    }

    /**
     * Find items where key is not null
     */
    public function whereNotNull(string $key): Collection
    {
        return $this->filter(function ($item) use ($key) {
            $itemValue = $item instanceof PlugModel ? $item->$key : ($item[$key] ?? null);

            return !is_null($itemValue);
        });
    }

    /**
     * Find items where key is between values
     */
    public function whereBetween(string $key, array $values): Collection
    {
        return $this->filter(function ($item) use ($key, $values) {
            $itemValue = $item instanceof PlugModel ? $item->$key : ($item[$key] ?? null);

            return $itemValue >= $values[0] && $itemValue <= $values[1];
        });
    }

    /**
     * Find first item by key-value
     */
    public function firstWhere(string $key, $operator = null, $value = null)
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        return $this->where($key, $operator, $value)->first();
    }

    /**
     * Reduce collection to single value
     */
    public function reduce(callable $callback, $initial = null)
    {
        return array_reduce($this->items, $callback, $initial);
    }

    /**
     * Flatten multi-dimensional collection
     */
    public function flatten(int $depth = INF): Collection
    {
        $result = [];

        foreach ($this->items as $item) {
            if (!is_array($item) && !$item instanceof Collection) {
                $result[] = $item;
            } else {
                $values = $item instanceof Collection ? $item->all() : $item;

                if ($depth === 1) {
                    $result = array_merge($result, array_values($values));
                } else {
                    $result = array_merge($result, (new static($values))->flatten($depth - 1)->all());
                }
            }
        }

        return new static($result);
    }

    /**
     * Merge with another collection
     */
    public function merge($items): Collection
    {
        if ($items instanceof Collection) {
            $items = $items->all();
        }

        return new static(array_merge($this->items, $items));
    }

    /**
     * Combine with values
     */
    public function combine($values): Collection
    {
        if ($values instanceof Collection) {
            $values = $values->all();
        }

        return new static(array_combine($this->items, $values));
    }

    /**
     * Get values only
     */
    public function values(): Collection
    {
        return new static(array_values($this->items));
    }

    /**
     * Get keys only
     */
    public function keys(): Collection
    {
        return new static(array_keys($this->items));
    }

    /**
     * Flip keys and values
     */
    public function flip(): Collection
    {
        return new static(array_flip($this->items));
    }

    /**
     * Pad collection to size
     */
    public function pad(int $size, $value): Collection
    {
        return new static(array_pad($this->items, $size, $value));
    }

    /**
     * Prepend item to collection
     */
    public function prepend($value, $key = null): Collection
    {
        if ($key === null) {
            array_unshift($this->items, $value);
        } else {
            $this->items = [$key => $value] + $this->items;
        }

        return $this;
    }

    /**
     * Push item to collection
     */
    public function push(...$values): Collection
    {
        foreach ($values as $value) {
            $this->items[] = $value;
        }

        return $this;
    }

    /**
     * Pop item from collection
     */
    public function pop()
    {
        return array_pop($this->items);
    }

    /**
     * Shift item from collection
     */
    public function shift()
    {
        return array_shift($this->items);
    }

    /**
     * Put item in collection
     */
    public function put($key, $value): Collection
    {
        $this->items[$key] = $value;

        return $this;
    }

    /**
     * Pull item from collection
     */
    public function pull($key, $default = null)
    {
        $value = $this->items[$key] ?? $default;
        unset($this->items[$key]);

        return $value;
    }

    /**
     * Get random item(s)
     */
    public function random(?int $count = null)
    {
        if ($count === null) {
            return $this->items[array_rand($this->items)];
        }

        $keys = array_rand($this->items, min($count, $this->count()));
        $keys = is_array($keys) ? $keys : [$keys];

        return new static(array_intersect_key($this->items, array_flip($keys)));
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
     * Tap into collection
     */
    public function tap(callable $callback): Collection
    {
        $callback(new static($this->items));

        return $this;
    }

    /**
     * Pipe collection through callable
     */
    public function pipe(callable $callback)
    {
        return $callback($this);
    }

    /**
     * Contains - check if item exists
     */
    public function contains($key, $operator = null, $value = null): bool
    {
        if (func_num_args() === 1) {
            if (is_callable($key)) {
                return $this->first($key) !== null;
            }

            return in_array($key, $this->items);
        }

        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        return $this->where($key, $operator, $value)->isNotEmpty();
    }

    /**
     * Check if collection doesn't contain item
     */
    public function doesntContain($key, $operator = null, $value = null): bool
    {
        return !$this->contains($key, $operator, $value);
    }

    /**
     * Get item at index
     */
    public function get($key, $default = null)
    {
        return $this->items[$key] ?? $default;
    }

    /**
     * Check if key exists
     */
    public function has($key): bool
    {
        return array_key_exists($key, $this->items);
    }

    /**
     * Forget item by key
     */
    public function forget($keys): Collection
    {
        $keys = (array) $keys;

        foreach ($keys as $key) {
            unset($this->items[$key]);
        }

        return $this;
    }

    /**
     * When - conditional execution
     */
    public function when($condition, callable $callback, ?callable $default = null): Collection
    {
        if ($condition) {
            return $callback($this) ?? $this;
        } elseif ($default) {
            return $default($this) ?? $this;
        }

        return $this;
    }

    /**
     * Unless - inverse conditional execution
     */
    public function unless($condition, callable $callback, ?callable $default = null): Collection
    {
        return $this->when(!$condition, $callback, $default);
    }

    /**
     * Set pivot context for many-to-many relationships
     * This is called internally by the model when creating a belongsToMany collection
     */
    public function setPivotContext($model, string $relation, array $config): Collection
    {
        $this->_pivotModel = $model;
        $this->_pivotRelation = $relation;
        $this->_pivotConfig = $config;

        return $this;
    }

    /**
     * Sync pivot table relationships
     * Replaces all existing relationships with the provided IDs
     *
     * @param array|int $ids Array of IDs or single ID to sync
     * @param bool $detaching Whether to detach records not in the list
     * @return array Returns ['attached' => [], 'detached' => [], 'updated' => []]
     */
    public function sync($ids, bool $detaching = true): array
    {
        $this->ensurePivotContext();

        // Normalize IDs to array
        if (!is_array($ids)) {
            $ids = [$ids];
        }

        extract($this->_pivotConfig); // $pivotTable, $foreignPivotKey, $relatedPivotKey, $parentKey

        $parentId = $this->_pivotModel->getAttribute($parentKey);

        if (!$parentId) {
            throw new \Exception("Parent model must exist before syncing relationships");
        }

        // Get current relationship IDs
        $currentIds = $this->getCurrentPivotIds();

        // Determine what to attach and detach
        $idsToAttach = array_diff($ids, $currentIds);
        $idsToDetach = $detaching ? array_diff($currentIds, $ids) : [];
        $idsToUpdate = array_intersect($ids, $currentIds);

        $changes = [
            'attached' => [],
            'detached' => [],
            'updated' => [],
        ];

        // Begin transaction
        $modelClass = get_class($this->_pivotModel);
        $modelClass::beginTransaction();

        try {
            // Detach removed relationships
            if (!empty($idsToDetach)) {
                $this->detachPivot($idsToDetach);
                $changes['detached'] = $idsToDetach;
            }

            // Attach new relationships
            if (!empty($idsToAttach)) {
                $this->attachPivot($idsToAttach);
                $changes['attached'] = $idsToAttach;
            }

            // Record updated (existing) relationships
            $changes['updated'] = $idsToUpdate;

            $modelClass::commit();

            // Clear cache if enabled
            $this->clearPivotCache();

            // Reload the collection with fresh data
            $this->reloadFromDatabase();

            return $changes;
        } catch (\Exception $e) {
            $modelClass::rollBack();

            throw $e;
        }
    }

    /**
     * Attach relationships to pivot table
     * Adds new relationships without removing existing ones
     *
     * @param array|int $ids Array of IDs or single ID to attach
     * @param array $attributes Additional pivot attributes
     * @param bool $touch Whether to update timestamps
     * @return Collection Returns self for chaining
     */
    public function attach($ids, array $attributes = [], bool $touch = true): Collection
    {
        $this->ensurePivotContext();

        // Normalize IDs to array
        if (!is_array($ids)) {
            $ids = [$ids];
        }

        extract($this->_pivotConfig); // $pivotTable, $foreignPivotKey, $relatedPivotKey, $parentKey

        $parentId = $this->_pivotModel->getAttribute($parentKey);

        if (!$parentId) {
            throw new \Exception("Parent model must exist before attaching relationships");
        }

        // Get existing relationship IDs to avoid duplicates
        $existingIds = $this->getCurrentPivotIds();

        // Only attach IDs that don't already exist
        $idsToAttach = array_diff($ids, $existingIds);

        if (empty($idsToAttach)) {
            return $this; // Nothing to attach
        }

        $modelClass = get_class($this->_pivotModel);
        $modelClass::beginTransaction();

        try {
            $this->attachPivot($idsToAttach, $attributes, $touch);

            $modelClass::commit();

            // Clear cache if enabled
            $this->clearPivotCache();

            // Reload the collection with fresh data
            $this->reloadFromDatabase();

            return $this;
        } catch (\Exception $e) {
            $modelClass::rollBack();

            throw $e;
        }
    }

    /**
     * Detach relationships from pivot table
     * Removes relationships without affecting others
     *
     * @param array|int|null $ids Array of IDs, single ID, or null to detach all
     * @return int Number of relationships detached
     */
    public function detach($ids = null): int
    {
        $this->ensurePivotContext();

        extract($this->_pivotConfig); // $pivotTable, $foreignPivotKey, $relatedPivotKey, $parentKey

        $parentId = $this->_pivotModel->getAttribute($parentKey);

        if (!$parentId) {
            return 0;
        }

        // Normalize IDs to array
        if ($ids !== null && !is_array($ids)) {
            $ids = [$ids];
        }

        $modelClass = get_class($this->_pivotModel);
        $modelClass::beginTransaction();

        try {
            $count = $this->detachPivot($ids);

            $modelClass::commit();

            // Clear cache if enabled
            $this->clearPivotCache();

            // Reload the collection with fresh data
            $this->reloadFromDatabase();

            return $count;
        } catch (\Exception $e) {
            $modelClass::rollBack();

            throw $e;
        }
    }

    /**
     * Toggle relationships in pivot table
     * Attaches if not present, detaches if present
     *
     * @param array|int $ids Array of IDs or single ID to toggle
     * @return array Returns ['attached' => [], 'detached' => []]
     */
    public function toggle($ids): array
    {
        $this->ensurePivotContext();

        // Normalize IDs to array
        if (!is_array($ids)) {
            $ids = [$ids];
        }

        extract($this->_pivotConfig); // $pivotTable, $foreignPivotKey, $relatedPivotKey, $parentKey

        $parentId = $this->_pivotModel->getAttribute($parentKey);

        if (!$parentId) {
            throw new \Exception("Parent model must exist before toggling relationships");
        }

        // Get current relationship IDs
        $currentIds = $this->getCurrentPivotIds();

        // Determine what to attach and detach
        $idsToAttach = array_diff($ids, $currentIds);
        $idsToDetach = array_intersect($ids, $currentIds);

        $changes = [
            'attached' => [],
            'detached' => [],
        ];

        $modelClass = get_class($this->_pivotModel);
        $modelClass::beginTransaction();

        try {
            // Detach existing
            if (!empty($idsToDetach)) {
                $this->detachPivot($idsToDetach);
                $changes['detached'] = $idsToDetach;
            }

            // Attach new
            if (!empty($idsToAttach)) {
                $this->attachPivot($idsToAttach);
                $changes['attached'] = $idsToAttach;
            }

            $modelClass::commit();

            // Clear cache if enabled
            $this->clearPivotCache();

            // Reload the collection with fresh data
            $this->reloadFromDatabase();

            return $changes;
        } catch (\Exception $e) {
            $modelClass::rollBack();

            throw $e;
        }
    }

    /**
     * Update existing pivot record attributes
     *
     * @param int $id The related model ID
     * @param array $attributes Attributes to update
     * @return bool
     */
    public function updatePivot(int $id, array $attributes): bool
    {
        $this->ensurePivotContext();

        extract($this->_pivotConfig); // $pivotTable, $foreignPivotKey, $relatedPivotKey, $parentKey

        $parentId = $this->_pivotModel->getAttribute($parentKey);

        if (!$parentId || empty($attributes)) {
            return false;
        }

        $setClauses = [];
        $bindings = [];

        foreach ($attributes as $key => $value) {
            $setClauses[] = "{$key} = ?";
            $bindings[] = $value;
        }

        // Add updated_at if not present
        if (!array_key_exists('updated_at', $attributes)) {
            $setClauses[] = "updated_at = ?";
            $bindings[] = date('Y-m-d H:i:s');
        }

        $bindings[] = $parentId;
        $bindings[] = $id;

        $sql = "UPDATE {$pivotTable} SET " . implode(', ', $setClauses) .
            " WHERE {$foreignPivotKey} = ? AND {$relatedPivotKey} = ?";

        try {
            $this->executePivotQuery($sql, $bindings);
            $this->clearPivotCache();
            $this->reloadFromDatabase();

            return true;
        } catch (\Exception $e) {
            error_log("Failed to update pivot: " . $e->getMessage());

            return false;
        }
    }

    // ==================== PRIVATE HELPER METHODS ====================

    /**
     * Ensure pivot context is set
     */
    private function ensurePivotContext(): void
    {
        if (!$this->_pivotModel || !$this->_pivotConfig) {
            throw new \Exception("This collection is not associated with a pivot relationship. Use Model->relation() to get a pivot-enabled collection.");
        }
    }

    /**
     * Get current pivot IDs
     */
    private function getCurrentPivotIds(): array
    {
        extract($this->_pivotConfig); // $pivotTable, $foreignPivotKey, $relatedPivotKey, $parentKey

        $parentId = $this->_pivotModel->getAttribute($parentKey);

        $sql = "SELECT {$relatedPivotKey} FROM {$pivotTable} WHERE {$foreignPivotKey} = ?";
        $stmt = $this->executePivotQuery($sql, [$parentId]);
        $results = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        return array_map('intval', $results);
    }

    /**
     * Attach pivot records
     */
    private function attachPivot(array $ids, array $attributes = [], bool $touch = true): void
    {
        if (empty($ids)) {
            return;
        }

        extract($this->_pivotConfig); // $pivotTable, $foreignPivotKey, $relatedPivotKey, $parentKey

        $parentId = $this->_pivotModel->getAttribute($parentKey);
        $records = [];
        $now = date('Y-m-d H:i:s');

        foreach ($ids as $id) {
            $record = array_merge($attributes, [
                $foreignPivotKey => $parentId,
                $relatedPivotKey => $id,
            ]);

            // Add timestamps if touching
            if ($touch) {
                $record['created_at'] = $now;
                $record['updated_at'] = $now;
            }

            $records[] = $record;
        }

        // Batch insert
        if (!empty($records)) {
            $columns = array_keys($records[0]);
            $columnList = implode(', ', $columns);
            $placeholders = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
            $values = implode(', ', array_fill(0, count($records), $placeholders));

            $sql = "INSERT INTO {$pivotTable} ({$columnList}) VALUES {$values}";

            $bindings = [];
            foreach ($records as $record) {
                foreach ($columns as $column) {
                    $bindings[] = $record[$column] ?? null;
                }
            }

            $this->executePivotQuery($sql, $bindings);
        }
    }

    /**
     * Detach pivot records
     */
    private function detachPivot(?array $ids = null): int
    {
        extract($this->_pivotConfig); // $pivotTable, $foreignPivotKey, $relatedPivotKey, $parentKey

        $parentId = $this->_pivotModel->getAttribute($parentKey);

        if ($ids === null) {
            // Detach all
            $sql = "DELETE FROM {$pivotTable} WHERE {$foreignPivotKey} = ?";
            $bindings = [$parentId];
        } else {
            // Detach specific IDs
            if (empty($ids)) {
                return 0;
            }

            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql = "DELETE FROM {$pivotTable} WHERE {$foreignPivotKey} = ? AND {$relatedPivotKey} IN ({$placeholders})";
            $bindings = array_merge([$parentId], $ids);
        }

        $stmt = $this->executePivotQuery($sql, $bindings);

        return $stmt->rowCount();
    }

    /**
     * Execute query using parent model's connection
     */
    private function executePivotQuery(string $sql, array $bindings = [])
    {
        // Use reflection to call protected method
        $reflection = new \ReflectionMethod($this->_pivotModel, 'executeQuery');
        $reflection->setAccessible(true);

        return $reflection->invoke($this->_pivotModel, $sql, $bindings);
    }

    /**
     * Clear cache if enabled
     */
    private function clearPivotCache(): void
    {
        $modelClass = get_class($this->_pivotModel);

        try {
            $reflection = new \ReflectionProperty($modelClass, 'cacheEnabled');
            $reflection->setAccessible(true);

            if ($reflection->getValue()) {
                $modelClass::flushCache();
            }
        } catch (\Exception $e) {
            // Cache property doesn't exist or isn't accessible, ignore
        }
    }

    /**
     * Reload collection from database with fresh data
     */
    private function reloadFromDatabase(): void
    {
        if (!$this->_pivotModel || !$this->_pivotRelation) {
            return;
        }

        try {
            // Get fresh data using the relation method
            $fresh = $this->_pivotModel->{$this->_pivotRelation};

            if ($fresh instanceof Collection) {
                $this->items = $fresh->all();
            }
        } catch (\Exception $e) {
            // Silently fail reload - not critical
            error_log("Failed to reload collection: " . $e->getMessage());
        }
    }

    /**
     * Convert collection to string
     */
    public function __toString(): string
    {
        return $this->toJson();
    }
    /**
     * Convert collection to standardized API response
     */
    public function toResponse(int $status = 200, ?string $message = null): \Plugs\Http\StandardResponse
    {
        return new \Plugs\Http\StandardResponse($this->jsonSerialize(), true, $status, $message);
    }
}
