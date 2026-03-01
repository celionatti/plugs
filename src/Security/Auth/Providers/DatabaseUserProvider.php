<?php

declare(strict_types=1);

namespace Plugs\Security\Auth\Providers;

use Plugs\Security\Auth\Authenticatable;
use Plugs\Security\Auth\Contracts\UserProviderInterface;
use Plugs\Security\Hash;

/**
 * DatabaseUserProvider
 *
 * A generic user provider that retrieves users from the database
 * using a configurable Eloquent-style model class.
 */
class DatabaseUserProvider implements UserProviderInterface
{
    /**
     * The model class used for user retrieval.
     *
     * @var string
     */
    protected string $model;

    /**
     * Create a new database user provider.
     *
     * @param string $model Fully-qualified model class name
     */
    public function __construct(string $model)
    {
        $this->model = $model;
    }

    /**
     * {@inheritDoc}
     */
    public function retrieveById(mixed $identifier): ?Authenticatable
    {
        $model = $this->createModel();

        $user = $model::find($identifier);

        return ($user instanceof Authenticatable) ? $user : null;
    }

    /**
     * {@inheritDoc}
     */
    public function retrieveByToken(mixed $identifier, string $token): ?Authenticatable
    {
        $model = $this->createModel();

        $user = $model::where($model->getAuthIdentifierName(), '=', $identifier)
            ->first();

        if (!$user instanceof Authenticatable) {
            return null;
        }

        $rememberToken = $user->getRememberToken();

        return $rememberToken && hash_equals($rememberToken, $token) ? $user : null;
    }

    /**
     * {@inheritDoc}
     */
    public function updateRememberToken(Authenticatable $user, string $token): void
    {
        $user->setRememberToken($token);

        // Persist using the model's save method if available
        if (method_exists($user, 'save')) {
            $user->save();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        if (empty($credentials)) {
            return null;
        }

        $model = $this->createModel();
        $query = $model::query();

        foreach ($credentials as $key => $value) {
            if (!str_contains($key, 'password')) {
                $query->where($key, '=', $value);
            }
        }

        $user = $query->first();

        return ($user instanceof Authenticatable) ? $user : null;
    }

    /**
     * {@inheritDoc}
     */
    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        if (!isset($credentials['password'])) {
            return false;
        }

        return Hash::verify($credentials['password'], $user->getAuthPassword());
    }

    /**
     * Create a new instance of the model.
     *
     * @return Authenticatable
     */
    protected function createModel(): Authenticatable
    {
        $class = $this->model;

        return new $class;
    }

    /**
     * Get the model class name.
     *
     * @return string
     */
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * Set the model class name.
     *
     * @param string $model
     * @return $this
     */
    public function setModel(string $model): static
    {
        $this->model = $model;

        return $this;
    }
}
