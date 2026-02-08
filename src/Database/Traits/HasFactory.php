<?php

declare(strict_types=1);

namespace Plugs\Database\Traits;

use Plugs\Database\Factory\PlugFactory;

/**
 * Trait HasFactory
 * 
 * Add this trait to your models to enable factory support.
 * 
 * @package Plugs\Database\Traits
 */
/**
 * @phpstan-ignore trait.unused
 */
trait HasFactory
{
    /**
     * Create a new factory instance for the model
     * 
     * @param int|null $count Number of models to create
     * @return PlugFactory
     */
    public static function factory(?int $count = null): PlugFactory
    {
        $factory = static::newFactory() ?: static::resolveFactory();

        if ($count !== null) {
            $factory->count($count);
        }

        return $factory;
    }

    /**
     * Create a new factory instance for the model (can be overridden)
     */
    protected static function newFactory(): ?PlugFactory
    {
        return null;
    }

    /**
     * Resolve the factory class for the model automatically
     */
    protected static function resolveFactory(): PlugFactory
    {
        $modelName = (new \ReflectionClass(static::class))->getShortName();

        // Try to find factory in App\Database\Factories or database/factories
        $factoryClass = "Database\\Factories\\{$modelName}Factory";

        if (!class_exists($factoryClass)) {
            // Check if it's in a different namespace (common for framework models)
            $factoryClass = "Plugs\\Database\\Factories\\{$modelName}Factory";
        }

        if (class_exists($factoryClass)) {
            return new $factoryClass();
        }

        // Generic fallback factory if specific one not found
        return new class extends PlugFactory {
            public function definition(): array
            {
                return [];
            }
        };
    }
}
