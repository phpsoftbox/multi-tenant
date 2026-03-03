<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Contracts;

use PhpSoftBox\MultiTenant\Context\TenantContext;

interface TenantContextStoreInterface
{
    public function set(TenantContext $context): void;

    public function get(): ?TenantContext;

    public function has(): bool;

    public function clear(): void;
}
