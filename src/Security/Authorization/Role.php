<?php

declare(strict_types=1);

namespace Plugs\Security\Authorization;

interface Role
{
    /**
     * Get the unique identifier for the role.
     *
     * @return string|int
     */
    public function getRoleId();

    /**
     * Get the display name of the role.
     *
     * @return string
     */
    public function getRoleName(): string;

    /**
     * Get the permissions associated with the role.
     *
     * @return iterable<Permission>
     */
    public function getRolePermissions(): iterable;
}
