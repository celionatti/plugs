<?php

declare(strict_types=1);

namespace Plugs\Tenancy\Traits;

use Plugs\Support\Facades\App;
use Plugs\Tenancy\TenantManager;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        $tenantManager = App::make(TenantManager::class);

        if ($tenantManager->isActive()) {
            static::addGlobalScope('tenant', function ($builder) use ($tenantManager) {
                $builder->where('tenant_id', $tenantManager->getTenantKey());
            });

            static::creating(function ($model) use ($tenantManager) {
                if (empty($model->tenant_id)) {
                    $model->tenant_id = $tenantManager->getTenantKey();
                }
            });
        }
    }
}
