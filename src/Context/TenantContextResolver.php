<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Context;

use PhpSoftBox\MultiTenant\Contracts\TenantContextStoreInterface;
use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;
use RuntimeException;

final readonly class TenantContextResolver
{
    public function __construct(
        private TenantContextStoreInterface $contextStore,
    ) {
    }

    public function has(): bool
    {
        return $this->contextStore->has();
    }

    public function context(): ?TenantContext
    {
        return $this->contextStore->get();
    }

    public function get(): ?TenantDefinition
    {
        return $this->context()?->tenant();
    }

    public function getOrFail(): TenantDefinition
    {
        $tenant = $this->get();
        if (!$tenant instanceof TenantDefinition) {
            throw new RuntimeException('Tenant context is missing.');
        }

        return $tenant;
    }
}
