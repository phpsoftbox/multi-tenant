<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Http\Resolver;

use PhpSoftBox\MultiTenant\Contracts\TenantProviderInterface;
use PhpSoftBox\MultiTenant\Contracts\TenantRequestResolverInterface;
use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;
use PhpSoftBox\MultiTenant\Tenant\TenantNotFoundException;
use Psr\Http\Message\ServerRequestInterface;

use function strtolower;
use function trim;

final readonly class HostTenantRequestResolver implements TenantRequestResolverInterface
{
    public function resolve(ServerRequestInterface $request, TenantProviderInterface $tenants): ?TenantDefinition
    {
        $host = strtolower(trim($request->getUri()->getHost()));
        if ($host === '') {
            throw TenantNotFoundException::forEmptyHost();
        }

        return $tenants->findByHost($host) ?? throw TenantNotFoundException::forHost($host);
    }
}
