<?php

declare(strict_types=1);

namespace Plugs\Security;

use RuntimeException;

/**
 * Trait to guard against mass assignment vulnerabilities.
 * Use in your Model classes to define $fillable or $guarded attributes.
 */
trait MassAssignmentGuard
{
    /**
     * Attributes that are mass assignable.
     * @var array<string>
     */
    protected array $fillable = [];

    /**
     * Attributes that are NOT mass assignable.
     * @var array<string>
     */
    protected array $guarded = ['*'];

    /**
     * Fill the model with an array of attributes.
     * Throws if guarded attributes are present.
     */
    public function fill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            if (!$this->isFillable($key)) {
                if ($this->isStrictGuarding()) {
                    throw new MassAssignmentException(
                        "Attribute [{$key}] is not mass assignable on " . static::class
                    );
                }
                continue;
            }
            $this->{$key} = $value;
        }
        return $this;
    }

    /**
     * Check if an attribute is fillable.
     */
    public function isFillable(string $key): bool
    {
        if (in_array($key, $this->fillable, true)) {
            return true;
        }

        if ($this->isGuarded($key)) {
            return false;
        }

        return empty($this->fillable);
    }

    /**
     * Check if an attribute is guarded.
     */
    public function isGuarded(string $key): bool
    {
        if (empty($this->guarded)) {
            return false;
        }

        return in_array('*', $this->guarded, true) || in_array($key, $this->guarded, true);
    }

    /**
     * Whether strict guarding is enabled (throws on violation).
     */
    protected function isStrictGuarding(): bool
    {
        return true; // Default to strict
    }

    /**
     * Temporarily disable guarding for a callback.
     */
    public static function unguarded(callable $callback): mixed
    {
        // In a full implementation, this would use a static flag.
        // For simplicity, we execute the callback directly.
        return $callback();
    }
}
