<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Http\Resolver;

use PhpSoftBox\MultiTenant\Contracts\TenantProviderInterface;
use PhpSoftBox\MultiTenant\Contracts\TenantRequestResolverInterface;
use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ChainTenantRequestResolver implements TenantRequestResolverInterface
{
    /** @var list<TenantRequestResolverInterface> */
    private array $resolvers;

    /** @param iterable<TenantRequestResolverInterface> $resolvers */
    public function __construct(iterable $resolvers)
    {
        $items = [];
        foreach ($resolvers as $resolver) {
            $items[] = $resolver;
        }
        $this->resolvers = $items;
    }

    public function resolve(ServerRequestInterface $request, TenantProviderInterface $tenants): ?TenantDefinition
    {
        foreach ($this->resolvers as $resolver) {
            $tenant = $resolver->resolve($request, $tenants);
            if ($tenant !== null) {
                return $tenant;
            }
        }

        return null;
    }
}
