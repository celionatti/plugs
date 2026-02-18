<?php

declare(strict_types=1);

namespace Plugs\Security\Authorization;

interface Permission
{
    /**
     * Get the unique identifier for the permission.
     *
     * @return string|int
     */
    public function getPermissionId();

    /**
     * Get the slug/name of the permission (e.g., 'edit.posts').
     *
     * @return string
     */
    public function getPermissionSlug(): string;
}
