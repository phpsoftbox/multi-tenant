<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Bootstrap;

use PhpSoftBox\MultiTenant\Context\TenantContext;
use PhpSoftBox\MultiTenant\Context\TenantRuntimeScope;
use PhpSoftBox\MultiTenant\Contracts\TenantBootstrapperInterface;
use PhpSoftBox\MultiTenant\Orm\TenantEntityManagerRegistryInterface;

final readonly class TenantOrmEntityManagerBootstrapper implements TenantBootstrapperInterface
{
    public function __construct(
        private TenantEntityManagerRegistryInterface $registry,
        private int $bootstrapPriority = 950,
    ) {
    }

    public function supports(TenantRuntimeScope $scope): bool
    {
        return true;
    }

    public function priority(): int
    {
        return $this->bootstrapPriority;
    }

    public function bootstrap(TenantContext $context): void
    {
        $this->registry->resetTenant();
    }

    public function teardown(TenantContext $context): void
    {
        $this->registry->resetTenant();
    }
}
