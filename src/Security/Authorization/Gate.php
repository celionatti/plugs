<?php

declare(strict_types=1);

namespace Plugs\Security\Authorization;

use Closure;
use Plugs\Security\Auth\Authenticatable;
use InvalidArgumentException;

class Gate
{
    /**
     * All of the defined abilities.
     *
     * @var array<string, Closure|string>
     */
    protected array $abilities = [];

    /**
     * All of the registered policies.
     *
     * @var array<string, string>
     */
    protected array $policies = [];

    /**
     * All of the registered before callbacks.
     *
     * @var array<Closure>
     */
    protected array $beforeCallbacks = [];

    /**
     * All of the registered after callbacks.
     *
     * @var array<Closure>
     */
    protected array $afterCallbacks = [];

    /**
     * The user resolver closure.
     *
     * @var Closure|null
     */
    protected ?Closure $userResolver = null;

    /**
     * Define a new ability.
     *
     * @param string $ability
     * @param callable|string $callback
     * @return $this
     */
    public function define(string $ability, callable|string $callback): self
    {
        $this->abilities[$ability] = $callback;

        return $this;
    }

    /**
     * Register a policy class for a given class.
     *
     * @param string $class
     * @param string $policy
     * @return $this
     */
    public function policy(string $class, string $policy): self
    {
        $this->policies[$class] = $policy;

        return $this;
    }

    /**
     * Determine if all of the given abilities should be granted for the current user.
     *
     * @param iterable|string $abilities
     * @param array $arguments
     * @return bool
     */
    public function check(iterable|string $abilities, array $arguments = []): bool
    {
        if (is_string($abilities)) {
            return $this->inspect($abilities, (array) $arguments);
        }

        return collect($abilities)->every(fn($ability) => $this->inspect($ability, (array) $arguments));
    }

    /**
     * Determine if any of the given abilities should be granted for the current user.
     *
     * @param iterable|string $abilities
     * @param array $arguments
     * @return bool
     */
    public function any(iterable|string $abilities, array $arguments = []): bool
    {
        return collect($abilities)->some(fn($ability) => $this->inspect($ability, (array) $arguments));
    }

    /**
     * Determine if the given ability should be granted for the current user.
     *
     * @param string $ability
     * @param array $arguments
     * @return bool
     */
    public function allows(string $ability, array $arguments = []): bool
    {
        return $this->check($ability, $arguments);
    }

    /**
     * Determine if the given ability should be denied for the current user.
     *
     * @param string $ability
     * @param array $arguments
     * @return bool
     */
    public function denies(string $ability, array $arguments = []): bool
    {
        return !$this->allows($ability, $arguments);
    }

    /**
     * Inspect the user for the given ability and arguments.
     *
     * @param string $ability
     * @param array $arguments
     * @return bool
     */
    protected function inspect(string $ability, array $arguments = []): bool
    {
        $user = $this->resolveUser();

        if (!$user) {
            return false;
        }

        // Run before callbacks
        foreach ($this->beforeCallbacks as $callback) {
            $result = $callback($user, $ability, $arguments);

            if (!is_null($result)) {
                return (bool) $result;
            }
        }

        $result = $this->callAbilityCallback($user, $ability, $arguments);

        // Run after callbacks
        foreach ($this->afterCallbacks as $callback) {
            $afterResult = $callback($user, $ability, $arguments, $result);

            if (!is_null($afterResult)) {
                $result = (bool) $afterResult;
            }
        }

        return (bool) $result;
    }

    /**
     * Call the callback for the given ability.
     *
     * @param Authenticatable $user
     * @param string $ability
     * @param array $arguments
     * @return bool
     */
    protected function callAbilityCallback(Authenticatable $user, string $ability, array $arguments): bool
    {
        // 1. Check for defined abilities (closures or class@method)
        if (isset($this->abilities[$ability])) {
            $callback = $this->abilities[$ability];

            if ($callback instanceof Closure) {
                return $callback($user, ...$arguments);
            }

            if (is_string($callback) && strpos($callback, '@') !== false) {
                return $this->callClassMethod($callback, $user, $arguments);
            }
        }

        // 2. Check for policies if we have arguments
        if (!empty($arguments)) {
            $firstArgument = $arguments[0];
            $class = is_object($firstArgument) ? get_class($firstArgument) : (is_string($firstArgument) ? $firstArgument : null);

            if ($class && isset($this->policies[$class])) {
                return $this->callPolicyMethod($this->policies[$class], $ability, $user, $arguments);
            }
        }

        // 3. Default to checking Authorizable if user implements it
        if ($user instanceof Authorizable) {
            return $user->hasPermission($ability, $arguments);
        }

        return false;
    }

    /**
     * Call a class@method callback.
     *
     * @param string $callback
     * @param Authenticatable $user
     * @param array $arguments
     * @return bool
     */
    protected function callClassMethod(string $callback, Authenticatable $user, array $arguments): bool
    {
        [$class, $method] = explode('@', $callback);

        $instance = app($class);

        return $instance->$method($user, ...$arguments);
    }

    /**
     * Call a policy method.
     *
     * @param string $policy
     * @param string $method
     * @param Authenticatable $user
     * @param array $arguments
     * @return bool
     */
    protected function callPolicyMethod(string $policy, string $method, Authenticatable $user, array $arguments): bool
    {
        $instance = app($policy);

        // Run policy-level 'before' check
        if (method_exists($instance, 'before')) {
            $result = $instance->before($user, $method, $arguments);
            if (!is_null($result)) {
                return (bool) $result;
            }
        }

        if (method_exists($instance, $method)) {
            return $instance->$method($user, ...$arguments);
        }

        return false;
    }

    /**
     * Register a callback to run before all gate checks.
     *
     * @param callable $callback
     * @return $this
     */
    public function before(callable $callback): self
    {
        $this->beforeCallbacks[] = $callback;

        return $this;
    }

    /**
     * Register a callback to run after all gate checks.
     *
     * @param callable $callback
     * @return $this
     */
    public function after(callable $callback): self
    {
        $this->afterCallbacks[] = $callback;

        return $this;
    }

    /**
     * Set the user resolver.
     *
     * @param Closure $resolver
     * @return $this
     */
    public function setUserResolver(Closure $resolver): self
    {
        $this->userResolver = $resolver;

        return $this;
    }

    /**
     * Resolve the user.
     *
     * @return Authenticatable|null
     */
    protected function resolveUser(): ?Authenticatable
    {
        if ($this->userResolver) {
            return ($this->userResolver)();
        }

        return function_exists('auth') ? auth()->user() : null;
    }

    /**
     * Get a gate instance for the given user.
     *
     * @param Authenticatable $user
     * @return static
     */
    public function forUser(Authenticatable $user): self
    {
        $gate = clone $this;

        return $gate->setUserResolver(fn() => $user);
    }
}
