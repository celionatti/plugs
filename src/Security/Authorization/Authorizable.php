<?php

declare(strict_types=1);

namespace Plugs\Security\Authorization;

interface Authorizable
{
    /**
     * Determine if the entity has a given permission.
     *
     * @param string $permission
     * @param array $arguments
     * @return bool
     */
    public function hasPermission(string $permission, array $arguments = []): bool;

    /**
     * Determine if the entity has any of the given roles.
     *
     * @param string|array $roles
     * @return bool
     */
    public function hasRole(string|array $roles): bool;

    /**
     * Get all roles assigned to the entity.
     *
     * @return iterable<Role>
     */
    public function getRoles(): iterable;

    /**
     * Get all permissions assigned to the entity (directly or via roles).
     *
     * @return iterable<Permission>
     */
    public function getPermissions(): iterable;
}
