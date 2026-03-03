<?php

declare(strict_types=1);

namespace Plugs\Security\Auth\Guards;

use Plugs\Event\DispatcherInterface;
use Plugs\Security\Auth\Authenticatable;
use Plugs\Security\Auth\Contracts\StatelessGuardInterface;
use Plugs\Security\Auth\Contracts\UserProviderInterface;
use Plugs\Security\Auth\Events\AuthAttempting;
use Plugs\Security\Auth\Events\AuthFailed;
use Plugs\Security\Auth\Events\AuthSucceeded;
use Plugs\Security\Auth\Events\LogoutOccurred;
use Plugs\Security\Jwt\JwtService;
use Psr\Http\Message\ServerRequestInterface;

/**
 * JwtGuard
 *
 * Stateless guard that authenticates users via JWT tokens.
 * Supports pluggable user providers and event dispatching.
 */
class JwtGuard implements StatelessGuardInterface
{
    protected string $name;
    protected UserProviderInterface $provider;
    protected JwtService $jwt;
    protected ?DispatcherInterface $events;
    protected ?Authenticatable $user = null;
    protected ?ServerRequestInterface $request = null;

    public function __construct(
        string $name,
        UserProviderInterface $provider,
        JwtService $jwt,
        ?DispatcherInterface $events = null,
    ) {
        $this->name = $name;
        $this->provider = $provider;
        $this->jwt = $jwt;
        $this->events = $events;
    }

    /**
     * Set the current request.
     */
    public function setRequest(ServerRequestInterface $request): self
    {
        $this->request = $request;

        return $this;
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

        $token = $this->getTokenForRequest();

        if (!empty($token)) {
            $payload = $this->jwt->decode($token);

            if ($payload && isset($payload['sub'])) {
                $this->user = $this->provider->retrieveById($payload['sub']);
            }
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

        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function validate(array $credentials = []): bool
    {
        $user = $this->provider->retrieveByCredentials($credentials);

        if ($user && $this->provider->validateCredentials($user, $credentials)) {
            return true;
        }

        // Timing-safe dummy check
        if (!$user && isset($credentials['password'])) {
            password_verify($credentials['password'], '$2y$10$fG6z.M5rUu2KqWnQ/G1u2O9wW3o3Y3.qR.z8/G1u2O9wW3o3Y3.qR.');
        }

        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function attempt(array $credentials = [], bool $remember = false): bool
    {
        $this->fireEvent(new AuthAttempting($this->name, $credentials));

        $user = $this->provider->retrieveByCredentials($credentials);

        if ($user && $this->provider->validateCredentials($user, $credentials)) {
            $this->login($user);
            $this->fireEvent(new AuthSucceeded($this->name, $user));

            return true;
        }

        $this->fireEvent(new AuthFailed($this->name, $credentials));

        // Timing-safe dummy check
        if (!$user && isset($credentials['password'])) {
            password_verify($credentials['password'], '$2y$10$fG6z.M5rUu2KqWnQ/G1u2O9wW3o3Y3.qR.z8/G1u2O9wW3o3Y3.qR.');
        }

        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function login(Authenticatable $user, bool $remember = false): void
    {
        $this->user = $user;
    }

    /**
     * {@inheritDoc}
     */
    public function logout(): void
    {
        $user = $this->user;
        $this->user = null;

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
     * Issue a JWT for the given user.
     *
     * @param Authenticatable $user
     * @param array $options (e.g. ['expiry' => 3600, 'claims' => [...]])
     * @return string The JWT token
     */
    public function issueToken(Authenticatable $user, array $options = []): string
    {
        $payload = [
            'sub' => $user->getAuthIdentifier(),
        ];

        if (isset($options['claims'])) {
            $payload = array_merge($payload, $options['claims']);
        }

        $expiry = $options['expiry'] ?? (int) config('jwt.ttl', 3600);

        return $this->jwt->encode($payload, $expiry);
    }

    /**
     * Refresh the current token.
     *
     * @param string|null $token
     * @param int $gracePeriod seconds after expiration that a token can still be refreshed
     * @return string|null The new JWT token
     */
    public function refresh(?string $token = null, int $gracePeriod = 300): ?string
    {
        $token = $token ?: $this->getTokenForRequest();

        if (empty($token)) {
            return null;
        }

        // Decode ignoring expiration to allow refresh of recently expired tokens
        $payload = $this->jwt->decode($token, true);

        if (!$payload || !isset($payload['sub'])) {
            return null;
        }

        // Ensure token isn't excessively old (prevent permanent refresh cycles if desired)
        // Here we just check the grace period against 'exp'
        if (isset($payload['exp']) && (time() - $payload['exp']) > $gracePeriod) {
            return null;
        }

        $user = $this->provider->retrieveById($payload['sub']);

        if (!$user) {
            return null;
        }

        return $this->issueToken($user);
    }

    /**
     * Revoke a JWT token.
     *
     * JWT tokens are stateless and cannot be revoked without a blacklist.
     * Override this method to implement token blacklisting if needed.
     *
     * @param string $token
     * @return bool
     */
    public function revokeToken(string $token): bool
    {
        // JWT tokens are stateless; revocation requires a blacklist
        // which can be implemented by extending this class
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function revokeAllTokens(Authenticatable $user): int
    {
        // JWT tokens are stateless; cannot revoke without blacklist
        return 0;
    }

    /**
     * Get the token for the current request.
     */
    public function getTokenForRequest(): ?string
    {
        if (!$this->request) {
            if (isset($GLOBALS['__current_request'])) {
                $this->request = $GLOBALS['__current_request'];
            } else {
                return null;
            }
        }

        $header = $this->request->getHeaderLine('Authorization');

        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        return null;
    }

    /**
     * Get the user provider.
     */
    public function getProvider(): UserProviderInterface
    {
        return $this->provider;
    }

    /**
     * Get the guard name.
     */
    public function getGuardName(): string
    {
        return $this->name;
    }

    protected function fireEvent(object $event): void
    {
        $this->events?->dispatch($event);
    }
}
