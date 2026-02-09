<?php

declare(strict_types=1);

namespace Plugs\Database\Traits;

use Plugs\Exceptions\AuthorizationException;
use Plugs\Facades\Auth;
use Plugs\Security\Authorization\Attributes\Authorize;
use ReflectionClass;
use ReflectionMethod;

trait Authorizable
{
    /**
     * Determine if the user has a given ability.
     */
    public function can(string $ability, ...$arguments): bool
    {
        $user = Auth::user();

        // 1. Check for method-based rule: can{Ability}
        $method = 'can' . ucfirst($ability);
        if (method_exists($this, $method)) {
            return $this->$method($user, ...$arguments);
        }

        // 2. Check for attribute-based rules on the class
        if ($this->checkAttributes(static::class, $ability, $user)) {
            return true;
        }

        // 3. Check for attribute-based rules on the specific ability method if it exists
        if (method_exists($this, $ability)) {
            if ($this->checkAttributes($this, $ability, $user)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if the user does not have a given ability.
     */
    public function cannot(string $ability, ...$arguments): bool
    {
        return !$this->can($ability, ...$arguments);
    }

    /**
     * Authorize a given action for the current user.
     *
     * @throws AuthorizationException
     */
    public function authorize(string $ability, ...$arguments): void
    {
        if (!$this->can($ability, ...$arguments)) {
            throw new AuthorizationException("This action is unauthorized.");
        }
    }

    /**
     * Internal helper to check attributes on a class or method.
     */
    protected function checkAttributes($target, string $ability, $user): bool
    {
        $reflection = is_string($target) ? new ReflectionClass($target) : new ReflectionClass($target);

        // If target is an object and we are checking a specific method
        $attributes = [];
        if (is_object($target) && method_exists($target, $ability)) {
            $methodReflection = $reflection->getMethod($ability);
            $attributes = $methodReflection->getAttributes(Authorize::class);
        } else {
            $attributes = $reflection->getAttributes(Authorize::class);
        }

        if (empty($attributes)) {
            return false;
        }

        foreach ($attributes as $attribute) {
            $instance = $attribute->newInstance();

            // Match by ability if specified in attribute
            if ($instance->ability !== null && $instance->ability !== $ability) {
                continue;
            }

            // Simple role/permission check (expects User model to have hasRole/hasPermission)
            if ($instance->role !== null) {
                if ($user && method_exists($user, 'hasRole') && $user->hasRole($instance->role)) {
                    return true;
                }
            }

            if ($instance->permission !== null) {
                if ($user && method_exists($user, 'hasPermission') && $user->hasPermission($instance->permission)) {
                    return true;
                }
            }

            // If ability matches but no role/permission specified, we consider it "defined" but logic might vary
            // For now, if ability matches and no further constraints, we return false to fall through
            // unless we want attribute-only definitions to mean "allow all authenticated".
        }

        return false;
    }
}
