<?php

declare(strict_types=1);

namespace Plugs\View;

use ReflectionClass;
use ReflectionProperty;

abstract class ReactiveComponent
{
    /**
     * Unique ID for the component instance
     */
    protected string $id;

    /**
     * Component name
     */
    protected string $name;

    public function __construct(string $name, array $data = [])
    {
        $this->name = $name;
        $this->id = uniqid('comp_');
        $this->fill($data);
    }

    /**
     * Fill component properties from data
     */
    public function fill(array $data): void
    {
        $reflection = new ReflectionClass($this);
        foreach ($data as $key => $value) {
            $key = (string) $key;
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
                    }
                }

                $this->$cleanKey = $value;
            }
        }
    }

    /**
     * Get all public properties for the frontend
     */
    public function getState(): array
    {
        $reflection = new ReflectionClass($this);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);
        $state = [];

        foreach ($properties as $property) {
            $name = $property->getName();
            $value = $property->getValue($this);

            $state[$name] = $this->dehydrate($value);
        }

        return $state;
    }

    /**
     * Dehydrate complex types for serialization
     */
    protected function dehydrate(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::ATOM);
        }

        if (is_array($value)) {
            return array_map([$this, 'dehydrate'], $value);
        }

        if (is_object($value) && method_exists($value, 'toArray')) {
            return $value->toArray();
        }

        return $value;
    }

    /**
     * Serialize state for the frontend
     */
    public function serializeState(): string
    {
        return base64_encode(json_encode($this->getState()));
    }

    /**
     * De-serialize state from the frontend
     */
    public function hydrate(string $serializedState): void
    {
        $data = json_decode(base64_decode($serializedState), true);
        if ($data) {
            $this->fill($data);
        }
    }

    /**
     * Render the component's view
     */
    abstract public function render();

    /**
     * Get the JS bridge script for this component
     */
    public function getJavaScript(): string
    {
        $state = json_encode($this->getState());
        return "window.PlugsReactive.init('{$this->id}', '{$this->name}', {$state});";
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
