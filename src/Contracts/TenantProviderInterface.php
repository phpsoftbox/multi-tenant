<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Contracts;

use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;

interface TenantProviderInterface
{
    /**
     * @return list<TenantDefinition>
     */
    public function all(bool $onlyEnabled = true): array;

    public function findById(string $id): ?TenantDefinition;

    public function findByHost(string $host): ?TenantDefinition;
}
