<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Contracts;

use PhpSoftBox\MultiTenant\Context\TenantContext;

interface BroadcastChannelSwitcherInterface
{
    public function activate(string $channelPrefix, TenantContext $context): void;

    public function deactivate(TenantContext $context): void;
}
