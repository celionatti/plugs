<?php

declare(strict_types=1);

namespace Plugs\Security\Auth\Guards;

use Plugs\Security\Auth\Authenticatable;
use Plugs\Security\Auth\GuardInterface;
use Plugs\Security\Jwt\JwtService;
use Psr\Http\Message\ServerRequestInterface;

class JwtGuard implements GuardInterface
{
    protected string $name;
    protected $provider; // User Provider (Model Class or similar)
    protected JwtService $jwt;
    protected ?Authenticatable $user = null;
    protected ?ServerRequestInterface $request = null;

    public function __construct(string $name, $provider, JwtService $jwt)
    {
        $this->name = $name;
        $this->provider = $provider;
        $this->jwt = $jwt;
    }

    public function setRequest(ServerRequestInterface $request): self
    {
        $this->request = $request;

        return $this;
    }

    public function check(): bool
    {
        return !is_null($this->user());
    }

    public function guest(): bool
    {
        return !$this->check();
    }

    public function user(): ?Authenticatable
    {
        if (!is_null($this->user)) {
            return $this->user;
        }

        $token = $this->getTokenForRequest();

        if (!empty($token)) {
            $payload = $this->jwt->decode($token);

            if ($payload && isset($payload['sub'])) {
                // Assuming provider is a Model Class string for now
                // In a real generic provider system, we'd have a UserProvider interface
                // But following Plugs style, we might use the Model directly or a simple convention
                if (class_exists($this->provider) && method_exists($this->provider, 'find')) {
                    $this->user = $this->provider::find($payload['sub']);
                }
            }
        }

        return $this->user;
    }

    public function id()
    {
        if ($this->user()) {
            return $this->user()->getAuthIdentifier();
        }

        return null;
    }

    public function validate(array $credentials = []): bool
    {
        // For JWT, validation usually means attempting to find a user 
        // and verifying password, returning true/false, BUT NOT logging them in (setting state)
        // However, usually 'attempt' is used for login.

        // This is a stateless guard, so validation is just checking coords.
        $user = $this->retrieveUserByCredentials($credentials);

        if ($user && $this->validateCredentials($user, $credentials)) {
            return true;
        }

        // Perform a dummy password check to prevent user enumeration via timing attacks if user is not found
        if (!$user && isset($credentials['password'])) {
            password_verify($credentials['password'], '$2y$10$fG6z.M5rUu2KqWnQ/G1u2O9wW3o3Y3.qR.z8/G1u2O9wW3o3Y3.qR.');
        }

        return false;
    }

    public function attempt(array $credentials = [], bool $remember = false): bool
    {
        // In JWT, "attempt" usually returns the token if successful, 
        // but the interface requires bool. 
        // So we might need a separate login method or just validation.

        // However, typical usage for attempt in session guards is:
        // if (Auth::attempt(...)) { redirect }

        // For API/JWT, we usually do:
        // if (Auth::validate(...)) { $token = ...; return response; }

        // We will implement standard check here.
        if ($this->validate($credentials)) {
            $user = $this->retrieveUserByCredentials($credentials);
            $this->login($user);
            return true;
        }

        return false;
    }

    public function login(Authenticatable $user, bool $remember = false): void
    {
        $this->user = $user;
    }

    public function logout(): void
    {
        $this->user = null;
    }

    public function setUser(Authenticatable $user): void
    {
        $this->user = $user;
    }

    /**
     * Get the token for the current request.
     */
    public function getTokenForRequest(): ?string
    {
        if (!$this->request) {
            // Try to get from global if not set
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

    // Helpers to mimic a provider
    protected function retrieveUserByCredentials(array $credentials)
    {
        if (!class_exists($this->provider)) {
            return null;
        }

        // Assume Eloquent-like Model
        $query = $this->provider::query();

        foreach ($credentials as $key => $value) {
            if (!str_contains($key, 'password')) {
                $query->where($key, $value);
            }
        }

        return $query->first();
    }

    protected function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        return password_verify($credentials['password'], $user->getAuthPassword());
    }
}
