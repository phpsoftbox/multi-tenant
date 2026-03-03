<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Bootstrap;

use PhpSoftBox\MultiTenant\Context\TenantContext;
use PhpSoftBox\MultiTenant\Context\TenantRuntimeScope;
use PhpSoftBox\MultiTenant\Contracts\BroadcastChannelSwitcherInterface;
use PhpSoftBox\MultiTenant\Contracts\TenantBootstrapperInterface;

use function str_replace;

final class TenantBroadcastChannelBootstrapper implements TenantBootstrapperInterface
{
    public function __construct(
        private readonly BroadcastChannelSwitcherInterface $switcher,
        private readonly string $prefixTemplate = 'tenant.{tenant}.',
        private readonly int $bootstrapPriority = 800,
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
        $prefix = str_replace('{tenant}', $context->tenant()->id, $this->prefixTemplate);
        $this->switcher->activate($prefix, $context);
    }

    public function teardown(TenantContext $context): void
    {
        $this->switcher->deactivate($context);
    }
}
