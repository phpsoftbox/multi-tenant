<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Bootstrap;

use PhpSoftBox\MultiTenant\Context\TenantContext;
use PhpSoftBox\MultiTenant\Context\TenantRuntimeScope;
use PhpSoftBox\MultiTenant\Contracts\TenantBootstrapperInterface;
use PhpSoftBox\MultiTenant\Contracts\TenantConnectionSwitcherInterface;
use PhpSoftBox\MultiTenant\Database\TenantDsnResolver;

final readonly class DatabaseTenantConnectionBootstrapper implements TenantBootstrapperInterface
{
    public function __construct(
        private TenantConnectionSwitcherInterface $switcher,
        private TenantDsnResolver $resolver,
    ) {
    }

    public function supports(TenantRuntimeScope $scope): bool
    {
        return true;
    }

    public function priority(): int
    {
        return 1_000;
    }

    public function bootstrap(TenantContext $context): void
    {
        $this->switcher->activate($this->resolver->resolve($context));
    }

    public function teardown(TenantContext $context): void
    {
        $this->switcher->deactivate();
    }
}
