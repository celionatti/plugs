<?php

declare(strict_types=1);

namespace Plugs\Tenancy\Resolvers;

use Plugs\Http\Message\ServerRequest;
use Plugs\Tenancy\Tenant;

class PathTenantResolver implements TenantResolver
{
    public function __construct(protected string $tenantModel)
    {
    }

    public function resolve(ServerRequest $request): ?Tenant
    {
        $path = $request->getUri()->getPath();
        $parts = explode('/', trim($path, '/'));

        if (empty($parts)) {
            return null;
        }

        $tenantSlug = $parts[0];

        if (method_exists($this->tenantModel, 'where')) {
            return $this->tenantModel::where('slug', $tenantSlug)->first();
        }

        return null;
    }
}
