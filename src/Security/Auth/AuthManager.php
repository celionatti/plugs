<?php

declare(strict_types=1);

namespace Plugs\Security\Auth;

use InvalidArgumentException;
use Plugs\Container\Container;
use Plugs\Event\DispatcherInterface;
use Plugs\Security\Auth\Contracts\UserProviderInterface;
use Plugs\Security\Auth\Guards\JwtGuard;
use Plugs\Security\Auth\Guards\KeyGuard;
use Plugs\Security\Auth\Guards\SessionGuard;
use Plugs\Security\Auth\Guards\TokenGuard;
use Plugs\Security\Auth\Providers\DatabaseUserProvider;
use Plugs\Security\Auth\Providers\KeyUserProvider;
use Plugs\Security\Identity\KeyDerivationService;
use Plugs\Security\Identity\NonceService;
use Plugs\Security\Jwt\JwtService;
use Plugs\Session\Session;

/**
 * AuthManager
 *
 * Central manager for the authentication system. Resolves guards
 * and user providers based on configuration, supports custom drivers
 * via extend(), and proxies all method calls to the default guard.
 *
 * Configuration is read from the 'auth' config key:
 *
 *     'defaults' => ['guard' => 'web'],
 *     'guards' => [
 *         'web' => ['driver' => 'session', 'provider' => 'users'],
 *         'api' => ['driver' => 'jwt', 'provider' => 'users'],
 *         'token' => ['driver' => 'token', 'provider' => 'users'],
 *         'key' => ['driver' => 'key', 'provider' => 'key_users'],
 *     ],
 *     'providers' => [
 *         'users' => ['driver' => 'database', 'model' => 'App\Models\User'],
 *         'key_users' => ['driver' => 'key', 'model' => 'App\Models\User'],
 *     ],
 */
class AuthManager
{
    /**
     * Resolved guard instances.
     *
     * @var array<string, GuardInterface>
     */
    protected array $guards = [];

    /**
     * Custom guard driver creators.
     *
     * @var array<string, callable>
     */
    protected array $customCreators = [];

    /**
     * Custom provider driver creators.
     *
     * @var array<string, callable>
     */
    protected array $customProviderCreators = [];

    /**
     * The default guard name.
     */
    protected string $defaultGuard;

    /**
     * The service container.
     */
    protected Container $container;

    /**
     * The event dispatcher.
     */
    protected ?DispatcherInterface $events;

    public function __construct(?Container $container = null, ?DispatcherInterface $events = null)
    {
        $this->container = $container ?: Container::getInstance();
        $this->events = $events;
        $this->defaultGuard = config('auth.defaults.guard', 'session');
    }

    // -------------------------------------------------------------------------
    // Guard Resolution
    // -------------------------------------------------------------------------

    /**
     * Get a guard instance by name.
     *
     * @param string|null $name Guard name (null = default)
     * @return GuardInterface
     */
    public function guard(?string $name = null): GuardInterface
    {
        $name = $name ?: $this->defaultGuard;

        if (!isset($this->guards[$name])) {
            $this->guards[$name] = $this->resolve($name);
        }

        return $this->guards[$name];
    }

    /**
     * Resolve a guard by its name.
     */
    protected function resolve(string $name): GuardInterface
    {
        $config = config("auth.guards.{$name}");

        // Resilient fallback for missing configurations
        if (is_null($config)) {
            $config = match ($name) {
                'web', 'session' => ['driver' => 'session', 'provider' => 'users'],
                'token', 'api' => ['driver' => 'token', 'provider' => 'users'],
                'jwt' => ['driver' => 'jwt', 'provider' => 'users'],
                'key' => ['driver' => 'key', 'provider' => 'key_users'],
                default => throw new InvalidArgumentException("Auth guard [{$name}] is not defined.")
            };
        }

        $driverName = $config['driver'] ?? $name;

        // Check for custom creator first
        if (isset($this->customCreators[$driverName])) {
            return $this->callCustomCreator($driverName, $name, $config);
        }

        // Built-in drivers
        $method = 'create' . ucfirst($driverName) . 'Driver';

        if (method_exists($this, $method)) {
            return $this->$method($name, $config);
        }

        throw new InvalidArgumentException(
            "Auth driver [{$driverName}] for guard [{$name}] is not supported."
        );
    }

    // -------------------------------------------------------------------------
    // Built-in Guard Drivers
    // -------------------------------------------------------------------------

    protected function createSessionDriver(string $name, array $config): SessionGuard
    {
        $provider = $this->createUserProvider($config['provider'] ?? 'users');

        return new SessionGuard(
            $name,
            $provider,
            $this->container->has(Session::class)
            ? $this->container->make(Session::class)
            : new Session(),
            $this->events,
        );
    }

    protected function createJwtDriver(string $name, array $config): JwtGuard
    {
        $provider = $this->createUserProvider($config['provider'] ?? 'users');

        return new JwtGuard(
            $name,
            $provider,
            $this->container->has(JwtService::class)
            ? $this->container->make(JwtService::class)
            : new JwtService(),
            $this->events,
        );
    }

    protected function createTokenDriver(string $name, array $config): TokenGuard
    {
        $provider = $this->createUserProvider($config['provider'] ?? 'users');

        return new TokenGuard(
            $name,
            $provider,
            $this->events,
            $config['model'] ?? null,
        );
    }

    protected function createKeyDriver(string $name, array $config): KeyGuard
    {
        $provider = $this->createUserProvider($config['provider'] ?? 'key_users');

        $keyService = $this->container->has(KeyDerivationService::class)
            ? $this->container->make(KeyDerivationService::class)
            : new KeyDerivationService();

        $nonceService = $this->container->has(NonceService::class)
            ? $this->container->make(NonceService::class)
            : new NonceService();

        return new KeyGuard(
            $name,
            $provider,
            $keyService,
            $nonceService,
            $this->events,
        );
    }

    // -------------------------------------------------------------------------
    // User Provider Resolution
    // -------------------------------------------------------------------------

    /**
     * Create a user provider by its configuration name.
     *
     * @param string $providerName The key in auth.providers config
     * @return UserProviderInterface
     */
    public function createUserProvider(string $providerName): UserProviderInterface
    {
        $config = config("auth.providers.{$providerName}");

        // Resilient fallback for missing provider configurations
        if (is_null($config)) {
            $config = match ($providerName) {
                'users' => ['driver' => 'database', 'model' => 'App\\Models\\User'],
                'key_users' => ['driver' => 'key', 'model' => 'App\\Models\\User'],
                default => ['driver' => 'database', 'model' => $providerName]
            };
        }

        $driver = $config['driver'] ?? 'database';

        // Check for custom provider creator
        if (isset($this->customProviderCreators[$driver])) {
            return ($this->customProviderCreators[$driver])($this->container, $config);
        }

        return match ($driver) {
            'database' => new DatabaseUserProvider($config['model'] ?? 'App\\Models\\User'),
            'key' => new KeyUserProvider($config['model'] ?? 'App\\Models\\User'),
            default => throw new InvalidArgumentException(
                "Auth user provider driver [{$driver}] is not supported."
            ),
        };
    }

    // -------------------------------------------------------------------------
    // Extensibility
    // -------------------------------------------------------------------------

    /**
     * Register a custom guard driver.
     *
     * The callback receives: (Container $container, string $guardName, array $config)
     * and must return a GuardInterface.
     *
     * @param string $driver Driver name (e.g. 'custom', 'ldap')
     * @param callable $callback
     * @return $this
     */
    public function extend(string $driver, callable $callback): static
    {
        $this->customCreators[$driver] = $callback;

        return $this;
    }

    /**
     * Register a custom user provider driver.
     *
     * The callback receives: (Container $container, array $config)
     * and must return a UserProviderInterface.
     *
     * @param string $driver Provider driver name
     * @param callable $callback
     * @return $this
     */
    public function provider(string $driver, callable $callback): static
    {
        $this->customProviderCreators[$driver] = $callback;

        return $this;
    }

    /**
     * Call a custom guard creator.
     */
    protected function callCustomCreator(string $driver, string $name, array $config): GuardInterface
    {
        $creator = $this->customCreators[$driver];

        // If the creator is a class name, instantiate it
        if (is_string($creator) && class_exists($creator)) {
            return $this->container->make($creator);
        }

        return $creator($this->container, $name, $config);
    }

    // -------------------------------------------------------------------------
    // Default Guard
    // -------------------------------------------------------------------------

    /**
     * Get the default guard name.
     */
    public function getDefaultGuard(): string
    {
        return $this->defaultGuard;
    }

    /**
     * Set the default guard name.
     */
    public function setDefaultGuard(string $name): void
    {
        $this->defaultGuard = $name;
    }

    // -------------------------------------------------------------------------
    // Proxy Methods (delegate to default guard)
    // -------------------------------------------------------------------------

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

    /**
     * Proxy all other method calls to the default guard.
     */
    public function __call(string $method, array $parameters)
    {
        return $this->guard()->$method(...$parameters);
    }
}
