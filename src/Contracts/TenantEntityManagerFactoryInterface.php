<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Contracts;

use PhpSoftBox\Database\Connection\ConnectionManagerInterface;
use PhpSoftBox\Orm\Contracts\EntityManagerInterface;

interface TenantEntityManagerFactoryInterface
{
    public function create(
        ConnectionManagerInterface $connections,
        string $connectionName,
    ): EntityManagerInterface;
}
