<?php

declare(strict_types=1);

namespace Plugs\Http\Resources;

use JsonSerializable;
use Plugs\Base\Model\PlugModel;
use Plugs\Database\Collection;
use Plugs\Http\StandardResponse;
use Plugs\Utils\Str;

/**
 * PlugResource
 * 
 * Base class for API resources that transform models/data for API responses.
 * Inspired by Laravel's API Resources with unique Plugs enhancements.
 * 
 * @package Plugs\Http\Resources
 */
abstract class PlugResource implements JsonSerializable
{
    /**
     * The underlying resource being transformed
     */
    public mixed $resource;

    /**
     * Additional data to append to the response
     */
    protected array $additional = [];

    /**
     * The wrapper key for the resource data (null = no wrapper)
     */
    public static ?string $wrap = 'data';

    /**
     * Whether to automatically convert snake_case keys to camelCase
     */
    public static bool $camelCase = true;

    /**
     * Whether to preserve original keys (overrides camelCase)
     */
    public bool $preserveKeys = false;

    /**
     * Response callback for customizing the HTTP response
     */
    protected ?\Closure $responseCallback = null;

    /**
     * Create a new resource instance
     */
    public function __construct(mixed $resource)
    {
        $this->resource = $resource;
    }

    /**
     * Create a new resource instance (static factory)
     */
    public static function make(mixed $resource): static
    {
        return new static($resource);
    }

    /**
     * Create a collection of resources
     */
    public static function collection(Collection|array $resources): PlugResourceCollection
    {
        return new AnonymousResourceCollection($resources, static::class);
    }

    /**
     * Transform the resource into an array
     * This method must be implemented by child classes
     */
    abstract public function toArray(): array;

    /**
     * Conditionally include a value
     * 
     * @param bool|callable $condition The condition to evaluate
     * @param mixed $value The value if condition is true (can be callable)
     * @param mixed $default The default value if condition is false
     * @return mixed
     */
    protected function when(bool|callable $condition, mixed $value, mixed $default = null): mixed
    {
        $condition = is_callable($condition) ? $condition() : $condition;

        if ($condition) {
            return is_callable($value) ? $value() : $value;
        }

        if (func_num_args() === 3) {
            return is_callable($default) ? $default() : $default;
        }

        return new MissingValue();
    }

    /**
     * Conditionally include a relationship when it's loaded
     * 
     * @param string $relationship The relationship name
     * @param mixed $value Optional value/transformation (defaults to relationship data)
     * @param mixed $default Default value if not loaded
     * @return mixed
     */
    protected function whenLoaded(string $relationship, mixed $value = null, mixed $default = null): mixed
    {
        if (!$this->resource instanceof PlugModel) {
            return new MissingValue();
        }

        // Check if relationship is loaded
        $relations = $this->resource->relations ?? [];
        $isLoaded = array_key_exists($relationship, $relations);

        if (!$isLoaded) {
            if (func_num_args() >= 3) {
                return is_callable($default) ? $default() : $default;
            }
            return new MissingValue();
        }

        // Get the relationship data
        $relationData = $relations[$relationship];

        // If value is provided, use it (can be a transformation callback)
        if ($value !== null) {
            return is_callable($value) ? $value($relationData) : $value;
        }

        // Default: return the relationship data (transformed if it's a model/collection)
        if ($relationData instanceof Collection) {
            return $relationData->jsonSerialize();
        }

        if ($relationData instanceof PlugModel) {
            return $relationData->toArray();
        }

        return $relationData;
    }

    /**
     * Conditionally include a value when it's not null
     */
    protected function whenNotNull(mixed $value, ?callable $callback = null): mixed
    {
        if ($value === null) {
            return new MissingValue();
        }

        return $callback ? $callback($value) : $value;
    }

    /**
     * Conditionally merge an array of values
     * 
     * @param bool|callable $condition The condition to evaluate
     * @param array|callable $values The values to merge
     * @return array|MissingValue
     */
    protected function mergeWhen(bool|callable $condition, array|callable $values): array|MissingValue
    {
        $condition = is_callable($condition) ? $condition() : $condition;

        if (!$condition) {
            return new MissingValue();
        }

        return is_callable($values) ? $values() : $values;
    }

    /**
     * Merge the given values unconditionally
     */
    protected function merge(array $values): array
    {
        return $values;
    }

    /**
     * Add additional data to the response
     */
    public function additional(array $data): static
    {
        $this->additional = array_merge($this->additional, $data);
        return $this;
    }

    /**
     * Set response callback for customizing the HTTP response
     */
    public function withResponse(\Closure $callback): static
    {
        $this->responseCallback = $callback;
        return $this;
    }

    /**
     * Get the additional data
     */
    public function getAdditional(): array
    {
        return $this->additional;
    }

    /**
     * Resolve the resource to an array, filtering out MissingValue instances
     */
    public function resolve(): array
    {
        $data = $this->toArray();

        $data = $this->filter($data);

        if (static::$camelCase && !$this->preserveKeys) {
            return $this->convertKeysToCamelCase($data);
        }

        return $data;
    }

    /**
     * Recursively convert array keys to camelCase
     */
    protected function convertKeysToCamelCase(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            $newKey = is_string($key) ? Str::camel($key) : $key;

            if (is_array($value)) {
                $value = $this->convertKeysToCamelCase($value);
            }

            $result[$newKey] = $value;
        }

        return $result;
    }

    /**
     * Filter out MissingValue instances from array
     */
    protected function filter(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            // Skip MissingValue instances
            if ($value instanceof MissingValue) {
                continue;
            }

            // Recursively filter nested arrays
            if (is_array($value)) {
                $filtered = $this->filter($value);

                // Handle merge arrays (numeric keys from mergeWhen)
                if ($this->isMergeArray($key, $value)) {
                    $result = array_merge($result, $filtered);
                } else {
                    $result[$key] = $filtered;
                }
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Check if this is a merge array (used by merge/mergeWhen)
     */
    protected function isMergeArray(mixed $key, array $value): bool
    {
        // If key is numeric and all values in the array are non-numeric keys, it's a merge
        return is_int($key) && !empty($value) && !array_is_list($value);
    }

    /**
     * Convert the resource to a StandardResponse
     */
    public function toResponse(int $status = 200, ?string $message = 'Success'): StandardResponse
    {
        $response = new StandardResponse(
            $this->resolveWithWrap(),
            true,
            $status,
            $message
        );

        // Apply additional data as meta
        if (!empty($this->additional)) {
            $response->withMeta($this->additional);
        }

        // Apply response callback if set
        if ($this->responseCallback) {
            ($this->responseCallback)($response);
        }

        return $response;
    }

    /**
     * Resolve with optional wrapper
     */
    protected function resolveWithWrap(): array
    {
        $data = $this->resolve();

        if (static::$wrap !== null) {
            return [static::$wrap => $data];
        }

        return $data;
    }

    /**
     * Convert to JSON string
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * JsonSerializable implementation
     */
    public function jsonSerialize(): mixed
    {
        return $this->resolve();
    }

    /**
     * Convert to string (JSON)
     */
    public function __toString(): string
    {
        return $this->toJson();
    }

    /**
     * Magic getter for resource properties
     */
    public function __get(string $name): mixed
    {
        if ($this->resource instanceof PlugModel || is_object($this->resource)) {
            return $this->resource->$name ?? null;
        }

        if (is_array($this->resource)) {
            return $this->resource[$name] ?? null;
        }

        return null;
    }

    /**
     * Magic isset for resource properties
     */
    public function __isset(string $name): bool
    {
        if ($this->resource instanceof PlugModel || is_object($this->resource)) {
            return isset($this->resource->$name);
        }

        if (is_array($this->resource)) {
            return isset($this->resource[$name]);
        }

        return false;
    }

    /**
     * Disable the wrapper for this resource
     */
    public static function withoutWrapping(): void
    {
        static::$wrap = null;
    }

    /**
     * Set a custom wrapper key
     */
    public static function wrap(string $key): void
    {
        static::$wrap = $key;
    }
}
