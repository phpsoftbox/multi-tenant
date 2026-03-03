<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tenant\Host;

use PhpSoftBox\MultiTenant\Contracts\TenantProviderInterface;

use function strtolower;
use function trim;

final readonly class TenantHostResolver
{
    public function __construct(
        private TenantProviderInterface $provider,
        private CentralDomainPolicy $centralDomains = new CentralDomainPolicy(),
    ) {
    }

    public function resolve(string $host): TenantHostResolution
    {
        $host = strtolower(trim($host));
        if ($host === '') {
            return TenantHostResolution::notFound($host);
        }

        if ($this->centralDomains->isCentral($host)) {
            return TenantHostResolution::central($host);
        }

        $tenant = $this->provider->findByHost($host);
        if ($tenant !== null) {
            return TenantHostResolution::tenant($host, $tenant);
        }

        return TenantHostResolution::notFound($host);
    }

    public function resolveOrFail(string $host): TenantHostResolution
    {
        $resolved = $this->resolve($host);
        if ($resolved->isNotFound()) {
            throw new TenantHostNotFoundException('Tenant host not found: ' . $host);
        }

        return $resolved;
    }
}
