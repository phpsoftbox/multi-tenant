<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Orm;

use PhpSoftBox\Orm\Contracts\EntityAwareEntityManagerRegistryInterface;
use PhpSoftBox\Orm\Contracts\EntityManagerInterface;

interface TenantEntityManagerRegistryInterface extends EntityAwareEntityManagerRegistryInterface
{
    public function tenant(bool $write = true): EntityManagerInterface;

    /**
     * Очищает UnitOfWork и сбрасывает только EntityManager текущего tenant connection
     * во всех read/write режимах.
     */
    public function resetTenant(): void;

    /**
     * Очищает UnitOfWork каждого сбрасываемого EntityManager перед удалением из кеша.
     * Пустое имя выполняет полный сброс, указанное имя — сброс connection и его runtime-вариантов.
     */
    public function reset(?string $connectionName = null): void;
}
