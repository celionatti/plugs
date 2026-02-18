<?php

declare(strict_types=1);

namespace Plugs\Security\Authorization\Traits;

use Plugs\Security\Authorization\Permission;
use Plugs\Security\Authorization\Role;

trait HasRolesAndPermissions
{
    /**
     * Determine if the user has the given permission.
     *
     * @param string $permission
     * @param array $arguments
     * @return bool
     */
    public function hasPermission(string $permission, array $arguments = []): bool
    {
        // Check direct permissions
        foreach ($this->getPermissions() as $p) {
            if ($p->getPermissionSlug() === $permission) {
                return true;
            }
        }

        // Check permissions via roles
        foreach ($this->getRoles() as $role) {
            foreach ($role->getRolePermissions() as $p) {
                if ($p->getPermissionSlug() === $permission) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Determine if the user has any of the given roles.
     *
     * @param string|array|int $roles
     * @return bool
     */
    public function hasRole(string|array|int $roles): bool
    {
        $roles = (array) $roles;

        foreach ($this->getRoles() as $role) {
            if (in_array($role->getRoleName(), $roles) || in_array($role->getRoleId(), $roles)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if the user has all of the given roles.
     *
     * @param array $roles
     * @return bool
     */
    public function hasAllRoles(array $roles): bool
    {
        foreach ($roles as $role) {
            if (!$this->hasRole($role)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get all roles assigned to the user.
     * This should be overridden by the model if it uses a relationship.
     *
     * @return iterable<Role>
     */
    public function getRoles(): iterable
    {
        return $this->roles ?? [];
    }

    /**
     * Get all direct permissions assigned to the user.
     * This should be overridden by the model if it uses a relationship.
     *
     * @return iterable<Permission>
     */
    public function getPermissions(): iterable
    {
        return $this->permissions ?? [];
    }

    /**
     * Determine if the user has a given permission.
     * This is an alias for gate()->forUser($this)->check().
     *
     * @param string $ability
     * @param array|mixed $arguments
     * @return bool
     */
    public function can(string $ability, $arguments = []): bool
    {
        return function_exists('gate') ? gate()->forUser($this)->check($ability, (array) $arguments) : false;
    }
}
