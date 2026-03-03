<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Contracts;

use PhpSoftBox\MultiTenant\Context\TenantContext;

interface CacheNamespaceSwitcherInterface
{
    public function activate(string $namespace, TenantContext $context): void;

    public function deactivate(TenantContext $context): void;
}
