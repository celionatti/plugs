<?php

declare(strict_types=1);

namespace Plugs\Database\Traits;

use Plugs\Tenancy\TenantManager;
use Plugs\Database\Attributes\TenantAware;
use ReflectionClass;

trait HasTenancy
{
    public static function bootHasTenancy(): void
    {
        $reflection = new ReflectionClass(static::class);
        $attributes = $reflection->getAttributes(TenantAware::class);

        if (empty($attributes)) {
            return;
        }

        $tenantAware = $attributes[0]->newInstance();
        $column = $tenantAware->column;

        // 1. Add Global Scope for filtering by tenant
        static::addGlobalScope('tenancy', function ($builder) use ($column) {
            $manager = app(TenantManager::class);
            if ($manager->isActive()) {
                return $builder->where($column, $manager->getTenantKey());
            }
            return $builder;
        });

        // 2. Automatically inject tenant_id on creating
        static::creating(function ($model) use ($column) {
            $manager = app(TenantManager::class);
            if ($manager->isActive() && !isset($model->attributes[$column])) {
                $model->setAttribute($column, $manager->getTenantKey());
            }
        });
    }

    /**
     * Scope a query to only include results for the current tenant.
     * Manual override if needed.
     */
    public function scopeForTenant($query, $tenantId)
    {
        $reflection = new ReflectionClass(static::class);
        $attributes = $reflection->getAttributes(TenantAware::class);
        $column = !empty($attributes) ? $attributes[0]->newInstance()->column : 'tenant_id';

        return $query->where($column, $tenantId);
    }
}

