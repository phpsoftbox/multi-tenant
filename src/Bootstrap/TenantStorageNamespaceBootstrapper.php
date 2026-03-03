<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Bootstrap;

use PhpSoftBox\MultiTenant\Context\TenantContext;
use PhpSoftBox\MultiTenant\Context\TenantRuntimeScope;
use PhpSoftBox\MultiTenant\Contracts\StorageNamespaceSwitcherInterface;
use PhpSoftBox\MultiTenant\Contracts\TenantBootstrapperInterface;

use function str_replace;

final class TenantStorageNamespaceBootstrapper implements TenantBootstrapperInterface
{
    public function __construct(
        private readonly StorageNamespaceSwitcherInterface $switcher,
        private readonly string $pathTemplate = 'tenant/{tenant}',
        private readonly int $bootstrapPriority = 700,
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
        $pathPrefix = str_replace('{tenant}', $context->tenant()->id, $this->pathTemplate);
        $this->switcher->activate($pathPrefix, $context);
    }

    public function teardown(TenantContext $context): void
    {
        $this->switcher->deactivate($context);
    }
}
