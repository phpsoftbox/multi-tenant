<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Contracts;

use PhpSoftBox\MultiTenant\Context\TenantContext;
use PhpSoftBox\MultiTenant\Context\TenantRuntimeScope;

interface TenantBootstrapperInterface
{
    public function supports(TenantRuntimeScope $scope): bool;

    public function priority(): int;

    public function bootstrap(TenantContext $context): void;

    public function teardown(TenantContext $context): void;
}
