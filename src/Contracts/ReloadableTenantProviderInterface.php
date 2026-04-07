<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Contracts;

interface ReloadableTenantProviderInterface
{
    public function reload(): void;
}
