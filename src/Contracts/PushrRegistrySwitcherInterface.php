<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Contracts;

use PhpSoftBox\MultiTenant\Context\TenantContext;

interface PushrRegistrySwitcherInterface
{
    /**
     * @param array{app_id:?string, secret:?string} $credentials
     */
    public function activate(array $credentials, TenantContext $context): void;

    public function deactivate(TenantContext $context): void;
}
