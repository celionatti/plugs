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
use Psr\Http\Message\ServerRequestInterface;

/**
 * TokenGuard
 *
 * Stateless guard that authenticates users via personal access tokens
 * stored in a database table ("personal_access_tokens").
 */
class TokenGuard implements StatelessGuardInterface
{
    protected string $name;
    protected UserProviderInterface $provider;
    protected ?DispatcherInterface $events;
    protected ?Authenticatable $user = null;
    protected ?ServerRequestInterface $request = null;

    /**
     * The model/table class for access tokens.
     */
    protected string $tokenModel;

    public function __construct(
        string $name,
        UserProviderInterface $provider,
        ?DispatcherInterface $events = null,
        ?string $tokenModel = null,
    ) {
        $this->name = $name;
        $this->provider = $provider;
        $this->events = $events;
        $this->tokenModel = $tokenModel ?? config('auth.token.model', 'App\\Models\\PersonalAccessToken');
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
            $hashedToken = hash('sha256', $token);

            // Look up the token in the database
            if (class_exists($this->tokenModel) && method_exists($this->tokenModel, 'where')) {
                $accessToken = $this->tokenModel::where('token', '=', $hashedToken)->first();

                if ($accessToken) {
                    // Check expiration
                    if ($this->isTokenExpired($accessToken)) {
                        return null;
                    }

                    // Update last used timestamp
                    if (method_exists($accessToken, 'save')) {
                        $accessToken->last_used_at = date('Y-m-d H:i:s');
                        $accessToken->save();
                    }

                    $tokenableId = $accessToken->tokenable_id ?? $accessToken->user_id ?? null;

                    if ($tokenableId) {
                        $this->user = $this->provider->retrieveById($tokenableId);
                    }
                }
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
     * Issue a new personal access token for the user.
     *
     * @param Authenticatable $user
     * @param array $options ['name' => 'token-name', 'abilities' => ['*'], 'expiry' => 3600]
     * @return string The plain-text token (only returned once; store on client side)
     */
    public function issueToken(Authenticatable $user, array $options = []): string
    {
        $plainTextToken = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $plainTextToken);

        if (class_exists($this->tokenModel) && method_exists($this->tokenModel, 'create')) {
            $this->tokenModel::create([
                'tokenable_type' => get_class($user),
                'tokenable_id' => $user->getAuthIdentifier(),
                'user_id' => $user->getAuthIdentifier(),
                'name' => $options['name'] ?? 'default',
                'token' => $hashedToken,
                'abilities' => json_encode($options['abilities'] ?? ['*']),
                'expires_at' => isset($options['expiry'])
                    ? date('Y-m-d H:i:s', time() + $options['expiry'])
                    : null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return $plainTextToken;
    }

    /**
     * Revoke a specific token.
     *
     * @param string $token The plain-text token
     * @return bool
     */
    public function revokeToken(string $token): bool
    {
        $hashedToken = hash('sha256', $token);

        if (class_exists($this->tokenModel) && method_exists($this->tokenModel, 'where')) {
            $accessToken = $this->tokenModel::where('token', '=', $hashedToken)->first();

            if ($accessToken && method_exists($accessToken, 'delete')) {
                $accessToken->delete();
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function revokeAllTokens(Authenticatable $user): int
    {
        $count = 0;

        if (class_exists($this->tokenModel) && method_exists($this->tokenModel, 'where')) {
            $tokens = $this->tokenModel::where('tokenable_id', '=', $user->getAuthIdentifier())->get();

            if ($tokens) {
                foreach ($tokens as $token) {
                    if (method_exists($token, 'delete')) {
                        $token->delete();
                        $count++;
                    }
                }
            }
        }

        return $count;
    }

    /**
     * Get the bearer token from the current request.
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

        // Also check query parameter as fallback
        $query = $this->request->getQueryParams();
        if (isset($query['api_token'])) {
            return $query['api_token'];
        }

        return null;
    }

    /**
     * Get the guard name.
     */
    public function getGuardName(): string
    {
        return $this->name;
    }

    /**
     * Get the user provider.
     */
    public function getProvider(): UserProviderInterface
    {
        return $this->provider;
    }

    /**
     * Check if the access token has expired.
     */
    protected function isTokenExpired(object $accessToken): bool
    {
        $expiresAt = $accessToken->expires_at ?? null;

        if ($expiresAt === null) {
            return false; // No expiration set
        }

        return strtotime($expiresAt) < time();
    }

    protected function fireEvent(object $event): void
    {
        $this->events?->dispatch($event);
    }
}
