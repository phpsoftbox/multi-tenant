<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Provision\Step;

use PhpSoftBox\MultiTenant\Contracts\TenantProvisionStepInterface;
use PhpSoftBox\MultiTenant\Provision\TenantProvisionContext;

final readonly class OverriddenPriorityProvisionStep implements TenantProvisionStepInterface
{
    public function __construct(
        private TenantProvisionStepInterface $inner,
        private int $priorityOverride,
    ) {
    }

    public function id(): string
    {
        return $this->inner->id();
    }

    public function priority(): int
    {
        return $this->priorityOverride;
    }

    public function run(TenantProvisionContext $context): void
    {
        $this->inner->run($context);
    }
}
