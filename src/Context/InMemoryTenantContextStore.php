<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Context;

use PhpSoftBox\MultiTenant\Contracts\TenantContextStoreInterface;

final class InMemoryTenantContextStore implements TenantContextStoreInterface
{
    private ?TenantContext $context = null;

    public function set(TenantContext $context): void
    {
        $this->context = $context;
    }

    public function get(): ?TenantContext
    {
        return $this->context;
    }

    public function has(): bool
    {
        return $this->context !== null;
    }

    public function clear(): void
    {
        $this->context = null;
    }
}
