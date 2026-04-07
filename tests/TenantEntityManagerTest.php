<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests;

use PhpSoftBox\Database\Contracts\ConnectionInterface;
use PhpSoftBox\MultiTenant\Orm\TenantEntityManager;
use PhpSoftBox\MultiTenant\Orm\TenantEntityManagerRegistryInterface;
use PhpSoftBox\Orm\Collection\EntityCollection;
use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Contracts\EntityManagerInterface;
use PhpSoftBox\Orm\Relation\Scope\RelationScopeResolverInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\UuidInterface;

#[CoversClass(TenantEntityManager::class)]
final class TenantEntityManagerTest extends TestCase
{
    #[Test]
    public function connectionDelegatesToTenantEntityManager(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);

        $tenantEntityManager = $this->createMock(CurrentEntityManagerInterface::class);
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

        $tenantEntityManager = $this->createMock(CurrentEntityManagerInterface::class);
        $tenantEntityManager->expects(self::once())->method('persist')->with($entity);

        $registry = $this->createMock(TenantEntityManagerRegistryInterface::class);
        $registry->expects(self::once())->method('tenant')->with(true)->willReturn($tenantEntityManager);

        $entityManager = new TenantEntityManager($registry);

        $entityManager->persist($entity);
    }

    #[Test]
    public function relationScopeResolverDelegatesToTenantEntityManager(): void
    {
        $resolver = $this->createMock(RelationScopeResolverInterface::class);

        $tenantEntityManager = $this->createMock(CurrentEntityManagerInterface::class);
        $tenantEntityManager->expects(self::once())->method('relationScopeResolver')->willReturn($resolver);

        $registry = $this->createMock(TenantEntityManagerRegistryInterface::class);
        $registry->expects(self::once())->method('tenant')->with(true)->willReturn($tenantEntityManager);

        $entityManager = new TenantEntityManager($registry);

        self::assertSame($resolver, $entityManager->relationScopeResolver());
    }

    #[Test]
    public function relationLoadingDelegatesToTenantEntityManager(): void
    {
        $entity = $this->createMock(EntityInterface::class);

        $tenantEntityManager = $this->createMock(CurrentEntityManagerInterface::class);
        $tenantEntityManager->expects(self::once())->method('loadMissing')->with($entity, ['products']);
        $tenantEntityManager->expects(self::once())->method('reload')->with($entity, ['products']);

        $registry = $this->createMock(TenantEntityManagerRegistryInterface::class);
        $registry->expects(self::exactly(2))->method('tenant')->with(true)->willReturn($tenantEntityManager);

        $entityManager = new TenantEntityManager($registry);

        $entityManager->loadMissing($entity, ['products']);
        $entityManager->reload($entity, ['products']);
    }

    #[Test]
    public function managedHydrationDelegatesToTenantEntityManager(): void
    {
        $entity     = $this->createMock(EntityInterface::class);
        $collection = new EntityCollection([$entity]);

        $tenantEntityManager = $this->createMock(CurrentEntityManagerInterface::class);
        $tenantEntityManager->expects(self::once())
            ->method('manageHydratedEntities')
            ->with([$entity])
            ->willReturn($collection);

        $registry = $this->createMock(TenantEntityManagerRegistryInterface::class);
        $registry->expects(self::once())->method('tenant')->with(true)->willReturn($tenantEntityManager);

        self::assertSame(
            $collection,
            new TenantEntityManager($registry)->manageHydratedEntities([$entity]),
        );
    }

    #[Test]
    public function findWithDeletedDelegatesToTenantEntityManager(): void
    {
        $entity = $this->createMock(EntityInterface::class);

        $tenantEntityManager = $this->createMock(CurrentEntityManagerInterface::class);
        $tenantEntityManager->expects(self::once())
            ->method('findWithDeleted')
            ->with(EntityInterface::class, 42)
            ->willReturn($entity);

        $registry = $this->createMock(TenantEntityManagerRegistryInterface::class);
        $registry->expects(self::once())->method('tenant')->with(true)->willReturn($tenantEntityManager);

        self::assertSame(
            $entity,
            new TenantEntityManager($registry)->findWithDeleted(EntityInterface::class, 42),
        );
    }
}

/**
 * Временная compile-time граница теста до публикации текущего EntityManagerInterface в зависимостях MultiTenant.
 */
interface CurrentEntityManagerInterface extends EntityManagerInterface
{
    public function findWithDeleted(string $entityClass, int|string|UuidInterface $id): ?EntityInterface;

    public function loadMissing(EntityInterface|iterable $entities, string|array $relations): void;

    public function reload(EntityInterface|iterable $entities, string|array $relations): void;

    /** @param iterable<EntityInterface> $entities */
    public function manageHydratedEntities(iterable $entities): EntityCollection;
}
