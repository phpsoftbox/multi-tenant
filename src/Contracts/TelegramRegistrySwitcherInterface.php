<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Contracts;

use PhpSoftBox\MultiTenant\Context\TenantContext;

interface TelegramRegistrySwitcherInterface
{
    /**
     * @param array{bots:list<array<string, mixed>>, default:?string} $config
     */
    public function activate(array $config, TenantContext $context): void;

    public function deactivate(TenantContext $context): void;
}
