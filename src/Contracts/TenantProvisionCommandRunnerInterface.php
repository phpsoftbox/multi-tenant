<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Contracts;

interface TenantProvisionCommandRunnerInterface
{
    public function run(string $command): void;
}
