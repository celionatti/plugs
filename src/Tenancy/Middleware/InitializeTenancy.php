<?php

declare(strict_types=1);

namespace Plugs\Tenancy\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Plugs\Tenancy\TenantManager;
use Plugs\Tenancy\Resolvers\TenantResolver;

class InitializeTenancy implements MiddlewareInterface
{
    public function __construct(
        protected TenantManager $tenantManager,
        protected TenantResolver $resolver
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        /** @var \Plugs\Http\Message\ServerRequest $request */
        $tenant = $this->resolver->resolve($request);

        if ($tenant) {
            $this->tenantManager->setTenant($tenant);
        }

        return $handler->handle($request);
    }
}
