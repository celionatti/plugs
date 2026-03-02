<?php

declare(strict_types=1);

namespace Plugs\Database\Middleware;

use Plugs\Database\Contracts\QueryMiddleware;
use Plugs\Database\QueryBuilder;
use Closure;

class TenantMiddleware implements QueryMiddleware
{
    public function __construct(protected int $tenantId)
    {
    }

    public function handle(QueryBuilder $builder, Closure $next)
    {
        $builder->where('tenant_id', $this->tenantId);

        return $next($builder);
    }
}
