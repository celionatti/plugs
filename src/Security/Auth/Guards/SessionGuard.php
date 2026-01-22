<?php

declare(strict_types=1);

namespace Plugs\Security\Auth\Guards;

use Plugs\Security\Auth\GuardInterface;
use Plugs\Security\Auth\Authenticatable;
use Plugs\Session\Session;
use Plugs\Security\Hash;

class SessionGuard implements GuardInterface
{
    protected string $name;
    protected Session $session;
    protected string $model;
    protected ?Authenticatable $user = null;

    public function __construct(string $name, Session $session, string $model)
    {
        $this->name = $name;
        $this->session = $session;
        $this->model = $model;
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

        $id = $this->session->get($this->getName());

        if (!is_null($id)) {
            $user = ($this->model)::find($id);
            if ($user instanceof Authenticatable) {
                $this->user = $user;
            }
        }

        return $this->user;
    }

    public function id()
    {
        if ($this->user()) {
            return $this->user()->getAuthIdentifier();
        }

        return $this->session->get($this->getName());
    }

    public function validate(array $credentials = []): bool
    {
        if (empty($credentials)) {
            return false;
        }

        $user = ($this->model)::where($this->getIdentifierName($credentials), $credentials[$this->getIdentifierName($credentials)])->first();

        if ($user && Hash::verify($credentials['password'], $user->getAuthPassword())) {
            return true;
        }

        return false;
    }

    public function attempt(array $credentials = [], bool $remember = false): bool
    {
        $user = ($this->model)::where($this->getIdentifierName($credentials), $credentials[$this->getIdentifierName($credentials)])->first();

        if ($user && Hash::verify($credentials['password'], $user->getAuthPassword())) {
            $this->login($user, $remember);
            return true;
        }

        return false;
    }

    public function login(Authenticatable $user, bool $remember = false): void
    {
        $this->updateSession($user->getAuthIdentifier());

        $this->setUser($user);
    }

    public function logout(): void
    {
        $this->session->remove($this->getName());

        $this->user = null;
    }

    public function setUser(Authenticatable $user): void
    {
        $this->user = $user;
    }

    protected function updateSession($id): void
    {
        $this->session->set($this->getName(), $id);
        $this->session->regenerate();
    }

    protected function getName(): string
    {
        return 'login_' . $this->name . '_' . sha1(static::class);
    }

    protected function getIdentifierName(array $credentials): string
    {
        return array_key_exists('email', $credentials) ? 'email' : 'username';
    }
}
