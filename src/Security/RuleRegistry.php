<?php

declare(strict_types=1);

namespace Plugs\Security;

use Plugs\Security\Rules\RuleInterface;

class RuleRegistry
{
    /**
     * The registered rules.
     */
    protected static array $rules = [
        'required' => \Plugs\Security\Rules\Required::class,
        'email' => \Plugs\Security\Rules\Email::class,
        'password' => \Plugs\Security\Rules\Password::class,
        'unique' => \Plugs\Security\Rules\Unique::class,
        'dimensions' => \Plugs\Security\Rules\Dimensions::class,
        'mimetypes' => \Plugs\Security\Rules\Mimetypes::class,
    ];

    /**
     * Register a new rule.
     */
    public static function register(string $name, string $class): void
    {
        static::$rules[$name] = $class;
    }

    /**
     * Resolve a rule instance.
     */
    public static function resolve(string $name, array $params = []): ?RuleInterface
    {
        $class = static::$rules[$name] ?? null;

        if (!$class || !class_exists($class)) {
            return null;
        }

        // Handle rules that need parameters in constructor or via methods
        // For now, simple instantiation
        return new $class(...$params);
    }

    /**
     * Check if a rule is registered.
     */
    public static function has(string $name): bool
    {
        return isset(static::$rules[$name]);
    }
}
