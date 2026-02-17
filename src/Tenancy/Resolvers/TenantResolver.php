<?php

declare(strict_types=1);

namespace Plugs\Tenancy\Resolvers;

use Plugs\Http\Message\ServerRequest;
use Plugs\Tenancy\Tenant;

interface TenantResolver
{
    /**
     * Resolve the tenant from the given request.
     */
    public function resolve(ServerRequest $request): ?Tenant;
}
