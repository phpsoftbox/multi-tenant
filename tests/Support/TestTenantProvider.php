<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests\Support;

use PhpSoftBox\MultiTenant\Contracts\TenantProviderInterface;
use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;

use function array_filter;
use function array_values;

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
        if (!$onlyEnabled) {
            return $this->tenants;
        }

        return array_values(array_filter(
            $this->tenants,
            static fn (TenantDefinition $tenant): bool => $tenant->enabled,
        ));
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
