<?php

declare(strict_types=1);

namespace Plugs\Database\Factory;

use Closure;
use Plugs\Base\Model\PlugModel;
use Plugs\Database\Collection;
use Plugs\Database\Connection;

/**
 * PlugFactory
 * 
 * Base class for model factories in Plugs.
 * 
 * @package Plugs\Database\Factory
 */
abstract class PlugFactory
{
    /**
     * The associated model class
     */
    protected ?string $model = null;

    /**
     * The number of models to create
     */
    protected int $count = 1;

    /**
     * State transformations
     */
    protected array $states = [];

    /**
     * Attribute overrides
     */
    protected array $overrides = [];

    /**
     * Sequence generator
     */
    protected ?SequenceGenerator $sequence = null;

    /**
     * Faker instance
     */
    protected Faker $faker;

    /**
     * Create a new factory instance
     */
    public function __construct()
    {
        $this->faker = new Faker();
    }

    /**
     * Static factory method
     */
    public static function new(): static
    {
        return new static();
    }

    /**
     * Define the model's default state
     */
    abstract public function definition(): array;

    /**
     * Set the number of models to create
     */
    public function count(int $count): static
    {
        $this->count = $count;
        return $this;
    }

    /**
     * Set attribute overrides
     */
    public function state(array|Closure $state): static
    {
        $this->states[] = $state;
        return $this;
    }

    /**
     * Set a sequence for attributes
     */
    public function sequence(...$sequences): static
    {
        $this->sequence = new SequenceGenerator($sequences);
        return $this;
    }

    /**
     * Create models and persist them in the database
     */
    public function create(array $attributes = []): PlugModel|Collection
    {
        $results = $this->make($attributes);

        if ($results instanceof Collection) {
            foreach ($results as $model) {
                $model->save();
            }
        } else {
            $results->save();
        }

        return $results;
    }

    /**
     * Create model instances without persisting them
     */
    public function make(array $attributes = []): PlugModel|Collection
    {
        if ($this->count === 1) {
            return $this->makeInstance($attributes);
        }

        $models = [];
        for ($i = 0; $i < $this->count; $i++) {
            $models[] = $this->makeInstance($attributes);
        }

        return new Collection($models);
    }

    /**
     * Create a single model instance
     */
    protected function makeInstance(array $attributes = []): PlugModel
    {
        $definition = $this->definition();

        // Apply sequence
        if ($this->sequence) {
            $definition = array_merge($definition, $this->sequence->next());
        }

        // Apply states
        foreach ($this->states as $state) {
            if ($state instanceof Closure) {
                $definition = array_merge($definition, $state($definition));
            } else {
                $definition = array_merge($definition, $state);
            }
        }

        // Apply manual overrides
        $finalAttributes = array_merge($definition, $attributes);

        $className = $this->model;
        if (!$className || !class_exists($className)) {
            // Fallback to anonymous object if model not specified or doesn't exist
            // In a real framework this might throw an exception, but for flexibility:
            return new class ($finalAttributes) extends PlugModel {};
        }

        return new $className($finalAttributes);
    }

    /**
     * Define a relationship on the factory
     */
    public function has(PlugFactory $factory, string $relationship): static
    {
        // Placeholder for relationship logic
        // This would typically register an 'afterCreating' callback
        return $this;
    }
}
