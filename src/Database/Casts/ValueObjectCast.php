<?php

declare(strict_types=1);

namespace Plugs\Database\Casts;

use Plugs\Database\Contracts\CastsAttributes;
use Exception;

/**
 * ValueObjectCast
 *
 * Facilitates casting attributes to Value Objects.
 */
class ValueObjectCast implements CastsAttributes
{
    /**
     * The value object class name.
     *
     * @var string
     */
    protected string $valueObjectClass;

    /**
     * Create a new cast instance.
     *
     * @param string $valueObjectClass
     */
    public function __construct(string $valueObjectClass)
    {
        $this->valueObjectClass = $valueObjectClass;
    }

    /**
     * Cast the given value to a Value Object.
     */
    public function get($model, string $key, $value, array $attributes)
    {
        if (is_null($value)) {
            return null;
        }

        if ($value instanceof $this->valueObjectClass) {
            return $value;
        }

        // Assume the value object has a constructor that accepts the raw value
        // or a static `fromRaw` method.
        if (method_exists($this->valueObjectClass, 'fromRaw')) {
            return ($this->valueObjectClass)::fromRaw($value);
        }

        return new $this->valueObjectClass($value);
    }

    /**
     * Prepare the value object for storage.
     */
    public function set($model, string $key, $value, array $attributes)
    {
        if (is_null($value)) {
            return null;
        }

        if ($value instanceof $this->valueObjectClass) {
            // Assume the value object has a `toRaw` method or can be cast to string/array
            if (method_exists($value, 'toRaw')) {
                return $value->toRaw();
            }

            if (method_exists($value, '__toString')) {
                return (string) $value;
            }

            if (method_exists($value, 'toArray')) {
                return json_encode($value->toArray());
            }

            throw new Exception("Value object [" . get_class($value) . "] must implement toRaw(), toArray(), or __toString() for storage.");
        }

        return $value;
    }
}
