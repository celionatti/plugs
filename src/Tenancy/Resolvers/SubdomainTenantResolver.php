<?php

declare(strict_types=1);

namespace Plugs\Tenancy\Resolvers;

use Plugs\Http\Message\ServerRequest;
use Plugs\Tenancy\Tenant;

class SubdomainTenantResolver implements TenantResolver
{
    public function __construct(protected string $tenantModel)
    {
    }

    public function resolve(ServerRequest $request): ?Tenant
    {
        $host = $request->getUri()->getHost();
        $parts = explode('.', $host);

        if (count($parts) < 3) {
            return null;
        }

        $subdomain = $parts[0];

        // Assuming the model has a static method to find by domain/subdomain
        // This is a generic implementation that will depend on the app's model structure
        if (method_exists($this->tenantModel, 'where')) {
            return $this->tenantModel::where('subdomain', $subdomain)->first();
        }

        return null;
    }
}
