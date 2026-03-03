<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Contracts;

use PhpSoftBox\MultiTenant\Provision\TenantProvisionContext;
use PhpSoftBox\MultiTenant\Provision\TenantProvisionPayload;

interface TenantProvisionRunnerInterface
{
    public function run(TenantProvisionPayload $payload): TenantProvisionContext;
}
