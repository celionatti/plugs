<?php

declare(strict_types=1);

namespace Plugs\Tenancy;

use Plugs\Support\Facades\Config;
use Plugs\Tenancy\Resolvers\TenantResolver;

class TenantManager
{
    protected ?Tenant $currentTenant = null;
    protected array $resolvers = [];

    public function __construct()
    {
        // No-op for now, resolvers added via initialize
    }

    /**
     * Set the current tenant.
     */
    public function setTenant(?Tenant $tenant): void
    {
        $this->currentTenant = $tenant;
    }

    /**
     * Get the currently active tenant.
     */
    public function getTenant(): ?Tenant
    {
        return $this->currentTenant;
    }

    /**
     * Check if a tenant is currently active.
     */
    public function isActive(): bool
    {
        return $this->currentTenant !== null;
    }

    /**
     * Get the tenant's unique key.
     */
    public function getTenantKey(): string|int|null
    {
        return $this->currentTenant?->getTenantKey();
    }

    /**
     * Revert to the central/system state.
     */
    public function endTenancy(): void
    {
        $this->currentTenant = null;
    }
}
