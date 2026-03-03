<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Contracts;

use PhpSoftBox\MultiTenant\Context\TenantContext;
use PhpSoftBox\MultiTenant\Context\TenantRuntimeScope;
use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;

interface TenantExtensionLoaderInterface
{
    public function key(): string;

    public function supports(TenantRuntimeScope $scope): bool;

    public function priority(): int;

    public function load(TenantDefinition $tenant, TenantContext $context): mixed;
}
