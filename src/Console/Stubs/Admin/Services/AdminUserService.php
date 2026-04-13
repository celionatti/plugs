<?php

declare(strict_types=1);

namespace Modules\Admin\Services;

use Modules\Auth\Models\User;
use Plugs\Database\Collection;

class AdminUserService
{
    /**
     * Get all users for admin management.
     *
     * @return array|Collection
     */
    public function getAllUsers(): array|Collection
    {
        return User::all();
    }

    /**
     * Find a user by ID.
     *
     * @param int $id
     * @return User|null
     */
    public function findUser(int $id): ?User
    {
        return User::find($id);
    }

    /**
     * Create a new user.
     *
     * @param array $data
     * @return User
     */
    public function createUser(array $data): User
    {
        if (isset($data['password']) && !empty($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        // Remove confirmation and other non-fillable fields if present
        unset($data['password_confirmation'], $data['_token'], $data['_method']);

        return User::create($data);
    }

    /**
     * Update an existing user.
     *
     * @param User $user
     * @param array $data
     * @return bool
     */
    public function updateUser(User $user, array $data): bool
    {
        if (isset($data['password']) && !empty($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        } else {
            unset($data['password']);
        }

        unset($data['password_confirmation'], $data['_token'], $data['_method']);

        return $user->update($data);
    }

    /**
     * Delete a user.
     *
     * @param User $user
     * @return bool
     */
    public function deleteUser(User $user): bool
    {
        return $user->delete();
    }
}
