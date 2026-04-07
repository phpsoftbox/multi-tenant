<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests\Support;

use PhpSoftBox\MultiTenant\Contracts\TenantProviderInterface;
use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;

final readonly class TestTenantProvider implements TenantProviderInterface
{
    /**
     * @param list<TenantDefinition> $tenants
     */
    public function __construct(
        private array $tenants,
    ) {
    }

    public function all(bool $onlyEnabled = true): array
    {
        return $this->tenants;
    }

    public function findById(string $id): ?TenantDefinition
    {
        foreach ($this->tenants as $tenant) {
            if ($tenant->id === $id) {
                return $tenant;
            }
        }

        return null;
    }

    public function findByHost(string $host): ?TenantDefinition
    {
        foreach ($this->tenants as $tenant) {
            if ($tenant->host === $host) {
                return $tenant;
            }
        }

        return null;
    }
}
