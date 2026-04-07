<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Http\Middleware;

use PhpSoftBox\MultiTenant\Context\TenantRuntimeScope;
use PhpSoftBox\MultiTenant\Contracts\ReloadableTenantProviderInterface;
use PhpSoftBox\MultiTenant\Contracts\TenantProviderInterface;
use PhpSoftBox\MultiTenant\Tenant\Runtime\TenantRuntimeExecutor;
use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;
use PhpSoftBox\MultiTenant\Tenant\TenantNotFoundException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function strtolower;
use function trim;

final readonly class TenantResolveMiddleware implements MiddlewareInterface
{
    public const string DEFAULT_TENANT_ATTRIBUTE = '_tenant';

    public function __construct(
        private TenantProviderInterface $tenants,
        private TenantRuntimeExecutor $tenantRuntime,
        private string $tenantAttribute = self::DEFAULT_TENANT_ATTRIBUTE,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->tenants instanceof ReloadableTenantProviderInterface) {
            $this->tenants->reload();
        }

        $host = strtolower(trim($request->getUri()->getHost()));
        if ($host === '') {
            throw TenantNotFoundException::forEmptyHost();
        }

        $tenant = $this->tenants->findByHost($host);
        if (!$tenant instanceof TenantDefinition) {
            throw TenantNotFoundException::forHost($host);
        }

        $request = $request->withAttribute($this->tenantAttribute, $tenant);

        return $this->tenantRuntime->run(
            tenant: $tenant,
            callback: static fn (mixed $_context): ResponseInterface => $handler->handle($request),
            scope: TenantRuntimeScope::Http,
        );
    }
}
