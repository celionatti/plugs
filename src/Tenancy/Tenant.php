<?php

declare(strict_types=1);

namespace Plugs\Tenancy;

interface Tenant
{
    /**
     * Get the tenant's unique identifier.
     */
    public function getTenantKey(): string|int;

    /**
     * Get the tenant's domain/host.
     */
    public function getTenantDomain(): ?string;

    /**
     * Get the tenant's database connection name or configuration.
     * Return null to use the default connection with row-level scoping.
     */
    public function getTenantDatabaseConfig(): ?array;
}
