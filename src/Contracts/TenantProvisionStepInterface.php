<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Contracts;

use PhpSoftBox\MultiTenant\Provision\TenantProvisionContext;

interface TenantProvisionStepInterface
{
    public function id(): string;

    public function priority(): int;

    public function run(TenantProvisionContext $context): void;
}
