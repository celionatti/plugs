<?php

declare(strict_types=1);

namespace Plugs\View;

/*
|--------------------------------------------------------------------------
| Component Base Class
|--------------------------------------------------------------------------
|
| Lightweight base class for standard (non-reactive) class-backed view
| components. Unlike ReactiveComponent, this has no state serialization
| or JavaScript bridge — it is purely server-side.
|
| Extend this class when your component needs computed data or logic
| beyond what a view-only template can provide.
|
| @package Plugs\View
*/

use ReflectionClass;
use ReflectionProperty;

abstract class Component
{
    /**
     * Extra HTML attributes that were not matched to properties.
     */
    protected array $attributes = [];

    /**
     * Internal keys injected by the framework that should not be treated as HTML attributes.
     */
    private const INTERNAL_KEYS = ['attributes', 'slot', 'view', '__slot_id', '__fragmentRenderer', '__sections', '__stacks'];

    /**
     * Fill component properties from data.
     */
    public function __construct(array $data = [])
    {
        $reflection = new ReflectionClass($this);

        foreach ($data as $key => $value) {
            $key = (string) $key;

            if (in_array($key, self::INTERNAL_KEYS, true)) {
                continue;
            }

            $cleanKey = ltrim($key, ':');

            if (property_exists($this, $cleanKey)) {
                $property = $reflection->getProperty($cleanKey);
                $type = $property->getType();

                if ($type instanceof \ReflectionNamedType) {
                    $typeName = $type->getName();
                    if ($typeName === 'int') {
                        $value = (int) $value;
                    } elseif ($typeName === 'float') {
                        $value = (float) $value;
                    } elseif ($typeName === 'bool') {
                        $value = (bool) $value;
                    } elseif ($typeName === 'array' && is_string($value)) {
                        $decoded = json_decode($value, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $value = $decoded;
                        }
                    }
                }

                $this->$cleanKey = $value;
            } elseif (is_scalar($value)) {
                $this->attributes[$key] = $value;
            }
        }
    }

    /**
     * Render the component.
     *
     * Return a view name (e.g. 'components.alert') or raw HTML string.
     * If not overridden, the framework will use the view-only template.
     *
     * @return string|View
     */
    public function render(): string|View
    {
        return '';
    }

    /**
     * Get all public properties as view data.
     *
     * @return array<string, mixed>
     */
    public function data(): array
    {
        $reflection = new ReflectionClass($this);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);
        $data = [];

        foreach ($properties as $property) {
            $data[$property->getName()] = $property->getValue($this);
        }

        return $data;
    }

    /**
     * Get extra HTML attributes.
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }
}
