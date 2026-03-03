<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Contracts;

use PhpSoftBox\MultiTenant\Context\TenantContext;

interface StorageNamespaceSwitcherInterface
{
    public function activate(string $pathPrefix, TenantContext $context): void;

    public function deactivate(TenantContext $context): void;
}
