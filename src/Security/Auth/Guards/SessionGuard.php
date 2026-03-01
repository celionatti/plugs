<?php

declare(strict_types=1);

namespace Plugs\Security\Auth\Guards;

use Plugs\Event\DispatcherInterface;
use Plugs\Security\Auth\Authenticatable;
use Plugs\Security\Auth\Contracts\StatefulGuardInterface;
use Plugs\Security\Auth\Contracts\UserProviderInterface;
use Plugs\Security\Auth\Events\AuthAttempting;
use Plugs\Security\Auth\Events\AuthFailed;
use Plugs\Security\Auth\Events\AuthSucceeded;
use Plugs\Security\Auth\Events\LogoutOccurred;
use Plugs\Security\Hash;
use Plugs\Session\Session;

/**
 * SessionGuard
 *
 * A stateful guard that authenticates users via server-side sessions.
 * Supports remember-me tokens, events, and pluggable user providers.
 */
class SessionGuard implements StatefulGuardInterface
{
    protected string $name;
    protected UserProviderInterface $provider;
    protected Session $session;
    protected ?DispatcherInterface $events;
    protected ?Authenticatable $user = null;
    protected bool $viaRemember = false;

    public function __construct(
        string $name,
        UserProviderInterface $provider,
        Session $session,
        ?DispatcherInterface $events = null,
    ) {
        $this->name = $name;
        $this->provider = $provider;
        $this->session = $session;
        $this->events = $events;
    }

    /**
     * {@inheritDoc}
     */
    public function check(): bool
    {
        return !is_null($this->user());
    }

    /**
     * {@inheritDoc}
     */
    public function guest(): bool
    {
        return !$this->check();
    }

    /**
     * {@inheritDoc}
     */
    public function user(): ?Authenticatable
    {
        if (!is_null($this->user)) {
            return $this->user;
        }

        $id = $this->session->get($this->getName());

        if (!is_null($id)) {
            $this->user = $this->provider->retrieveById($id);
        }

        return $this->user;
    }

    /**
     * {@inheritDoc}
     */
    public function id()
    {
        if ($this->user()) {
            return $this->user()->getAuthIdentifier();
        }

        return $this->session->get($this->getName());
    }

    /**
     * {@inheritDoc}
     */
    public function validate(array $credentials = []): bool
    {
        if (empty($credentials)) {
            return false;
        }

        $user = $this->provider->retrieveByCredentials($credentials);

        if ($user && $this->provider->validateCredentials($user, $credentials)) {
            return true;
        }

        // Perform a dummy hash check to prevent user enumeration via timing attacks
        if (!$user && isset($credentials['password'])) {
            Hash::verify($credentials['password'], '$2y$10$fG6z.M5rUu2KqWnQ/G1u2O9wW3o3Y3.qR.z8/G1u2O9wW3o3Y3.qR.');
        }

        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function attempt(array $credentials = [], bool $remember = false): bool
    {
        $this->fireEvent(new AuthAttempting($this->name, $credentials, $remember));

        $user = $this->provider->retrieveByCredentials($credentials);

        if ($user && $this->provider->validateCredentials($user, $credentials)) {
            $this->login($user, $remember);
            $this->fireEvent(new AuthSucceeded($this->name, $user, $remember));

            return true;
        }

        $this->fireEvent(new AuthFailed($this->name, $credentials));

        // Perform a dummy hash check to prevent user enumeration via timing attacks
        if (!$user && isset($credentials['password'])) {
            Hash::verify($credentials['password'], '$2y$10$fG6z.M5rUu2KqWnQ/G1u2O9wW3o3Y3.qR.z8/G1u2O9wW3o3Y3.qR.');
        }

        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function login(Authenticatable $user, bool $remember = false): void
    {
        $this->updateSession($user->getAuthIdentifier());

        if ($remember) {
            $token = bin2hex(random_bytes(30));
            $this->provider->updateRememberToken($user, $token);
        }

        $this->setUser($user);
    }

    /**
     * {@inheritDoc}
     */
    public function logout(): void
    {
        $user = $this->user;

        $this->session->remove($this->getName());

        if ($this->user instanceof Authenticatable) {
            $rememberToken = $this->user->getRememberToken();
            if ($rememberToken) {
                $this->provider->updateRememberToken($this->user, '');
            }
        }

        $this->user = null;
        $this->viaRemember = false;

        $this->fireEvent(new LogoutOccurred($this->name, $user));
    }

    /**
     * {@inheritDoc}
     */
    public function setUser(Authenticatable $user): void
    {
        $this->user = $user;
    }

    /**
     * {@inheritDoc}
     */
    public function viaRemember(): bool
    {
        return $this->viaRemember;
    }

    /**
     * {@inheritDoc}
     */
    public function loginUsingId(mixed $id, bool $remember = false): ?Authenticatable
    {
        $user = $this->provider->retrieveById($id);

        if ($user) {
            $this->login($user, $remember);
            return $user;
        }

        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function setUserOnce(Authenticatable $user): void
    {
        $this->user = $user;
    }

    /**
     * Get the user provider.
     *
     * @return UserProviderInterface
     */
    public function getProvider(): UserProviderInterface
    {
        return $this->provider;
    }

    /**
     * Set the user provider.
     *
     * @param UserProviderInterface $provider
     * @return void
     */
    public function setProvider(UserProviderInterface $provider): void
    {
        $this->provider = $provider;
    }

    /**
     * Get the guard name.
     *
     * @return string
     */
    public function getGuardName(): string
    {
        return $this->name;
    }

    // -------------------------------------------------------------------------
    // Internal Helpers
    // -------------------------------------------------------------------------

    protected function updateSession(mixed $id): void
    {
        $this->session->set($this->getName(), $id);
        $this->session->regenerate();
    }

    protected function getName(): string
    {
        return 'login_' . $this->name . '_' . sha1(static::class);
    }

    protected function fireEvent(object $event): void
    {
        $this->events?->dispatch($event);
    }
}
