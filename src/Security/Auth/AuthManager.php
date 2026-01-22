<?php

declare(strict_types=1);

namespace Plugs\Security\Auth;

use Plugs\Security\Auth\Guards\SessionGuard;
use Plugs\Session\Session;
use InvalidArgumentException;

class AuthManager
{
    protected array $guards = [];
    protected array $customCreators = [];
    protected string $defaultGuard = 'web';

    public function __construct()
    {
        // Register default web guard
        $this->extend('web', function () {
            return new SessionGuard(
                'web',
                new Session(),
                config('auth.providers.users.model', 'App\\Models\\User')
            );
        });
    }

    public function guard(?string $name = null): GuardInterface
    {
        $name = $name ?: $this->defaultGuard;

        if (!isset($this->guards[$name])) {
            $this->guards[$name] = $this->resolve($name);
        }

        return $this->guards[$name];
    }

    protected function resolve(string $name): GuardInterface
    {
        if (isset($this->customCreators[$name])) {
            return $this->callCustomCreator($name);
        }

        $config = config("auth.guards.{$name}");

        if (is_null($config)) {
            throw new InvalidArgumentException("Auth guard [{$name}] is not defined.");
        }

        $driverMethod = 'create' . ucfirst($config['driver']) . 'Driver';

        if (method_exists($this, $driverMethod)) {
            return $this->$driverMethod($name, $config);
        }

        throw new InvalidArgumentException("Auth driver [{$config['driver']}] for guard [{$name}] is not supported.");
    }

    protected function createSessionDriver(string $name, array $config): SessionGuard
    {
        return new SessionGuard(
            $name,
            new Session(),
            config("auth.providers.{$config['provider']}.model", 'App\\Models\\User')
        );
    }

    public function extend(string $name, callable $callback): void
    {
        $this->customCreators[$name] = $callback;
    }

    protected function callCustomCreator(string $name): GuardInterface
    {
        return $this->customCreators[$name]($this);
    }

    public function getDefaultGuard(): string
    {
        return $this->defaultGuard;
    }

    public function setDefaultGuard(string $name): void
    {
        $this->defaultGuard = $name;
    }

    public function check(): bool
    {
        return $this->guard()->check();
    }

    public function guest(): bool
    {
        return $this->guard()->guest();
    }

    public function user(): ?Authenticatable
    {
        return $this->guard()->user();
    }

    public function id()
    {
        return $this->guard()->id();
    }

    public function validate(array $credentials = []): bool
    {
        return $this->guard()->validate($credentials);
    }

    public function attempt(array $credentials = [], bool $remember = false): bool
    {
        return $this->guard()->attempt($credentials, $remember);
    }

    public function login(Authenticatable $user, bool $remember = false): void
    {
        $this->guard()->login($user, $remember);
    }

    public function logout(): void
    {
        $this->guard()->logout();
    }

    public function setUser(Authenticatable $user): void
    {
        $this->guard()->setUser($user);
    }

    public function __call(string $method, array $parameters)
    {
        return $this->guard()->$method(...$parameters);
    }
}
