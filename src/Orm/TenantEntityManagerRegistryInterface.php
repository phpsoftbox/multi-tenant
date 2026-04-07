<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Orm;

use PhpSoftBox\Orm\Contracts\EntityManagerInterface;
use PhpSoftBox\Orm\Contracts\EntityManagerRegistryInterface;

interface TenantEntityManagerRegistryInterface extends EntityManagerRegistryInterface
{
    public function tenant(bool $write = true): EntityManagerInterface;

    public function reset(?string $connectionName = null): void;
}
