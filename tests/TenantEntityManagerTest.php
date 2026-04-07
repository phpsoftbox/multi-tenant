<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests;

use PhpSoftBox\Database\Contracts\ConnectionInterface;
use PhpSoftBox\MultiTenant\Orm\TenantEntityManager;
use PhpSoftBox\MultiTenant\Orm\TenantEntityManagerRegistryInterface;
use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Contracts\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TenantEntityManager::class)]
final class TenantEntityManagerTest extends TestCase
{
    #[Test]
    public function connectionDelegatesToTenantEntityManager(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);

        $tenantEntityManager = $this->createMock(EntityManagerInterface::class);
        $tenantEntityManager->expects(self::once())->method('connection')->willReturn($connection);

        $registry = $this->createMock(TenantEntityManagerRegistryInterface::class);
        $registry->expects(self::once())->method('tenant')->with(true)->willReturn($tenantEntityManager);

        $entityManager = new TenantEntityManager($registry);

        self::assertSame($connection, $entityManager->connection());
    }

    #[Test]
    public function persistDelegatesToTenantEntityManager(): void
    {
        $entity = $this->createMock(EntityInterface::class);

        $tenantEntityManager = $this->createMock(EntityManagerInterface::class);
        $tenantEntityManager->expects(self::once())->method('persist')->with($entity);

        $registry = $this->createMock(TenantEntityManagerRegistryInterface::class);
        $registry->expects(self::once())->method('tenant')->with(true)->willReturn($tenantEntityManager);

        $entityManager = new TenantEntityManager($registry);

        $entityManager->persist($entity);
    }
}
