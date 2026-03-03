<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Contracts;

use PhpSoftBox\MultiTenant\Context\TenantContext;

interface NotificationConfigSwitcherInterface
{
    /**
     * @param array<string, mixed> $overrides
     */
    public function activate(array $overrides, TenantContext $context): void;

    public function deactivate(TenantContext $context): void;
}
