<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tenant;

use PhpSoftBox\Database\Connection\ConnectionManagerInterface;
use PhpSoftBox\MultiTenant\Contracts\TenantEntityManagerFactoryInterface;
use PhpSoftBox\Orm\Contracts\EntityManagerInterface;
use PhpSoftBox\Orm\EntityManager;

final class DefaultTenantEntityManagerFactory implements TenantEntityManagerFactoryInterface
{
    public function create(
        ConnectionManagerInterface $connections,
        string $connectionName,
    ): EntityManagerInterface {
        return new EntityManager($connections->read($connectionName));
    }
}
