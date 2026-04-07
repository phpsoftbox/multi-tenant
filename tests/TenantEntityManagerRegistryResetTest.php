<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests;

use PhpSoftBox\MultiTenant\Orm\TenantEntityManagerRegistry;
use PhpSoftBox\MultiTenant\Tests\Support\TenantRuntimeEntity;
use PhpSoftBox\Orm\Contracts\EntityAwareEntityManagerRegistryInterface;
use PhpSoftBox\Orm\Contracts\EntityManagerInterface;
use PhpSoftBox\Orm\Contracts\UnitOfWorkInterface;
use PhpSoftBox\Orm\UnitOfWork\EntityHeap;
use PhpSoftBox\Orm\UnitOfWork\EntityRuntimeRegistry;
use PhpSoftBox\Orm\UnitOfWork\UnitOfWork;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TenantEntityManagerRegistry::class)]
#[CoversMethod(TenantEntityManagerRegistry::class, 'resetTenant')]
#[CoversMethod(TenantEntityManagerRegistry::class, 'reset')]
final class TenantEntityManagerRegistryResetTest extends TestCase
{
    /**
     * Проверяет освобождение entity из общего runtime registry перед созданием нового tenant UnitOfWork.
     *
     * @see TenantEntityManagerRegistry::resetTenant()
     * @see UnitOfWorkInterface::clear()
     */
    #[Test]
    public function resetTenantAllowsAnotherUnitOfWorkToManageSameEntityInstance(): void
    {
        $runtimeRegistry = new EntityRuntimeRegistry();

        $firstUnitOfWork = new UnitOfWork(new EntityHeap($runtimeRegistry));
        $nextUnitOfWork  = new UnitOfWork(new EntityHeap($runtimeRegistry));
        $firstManager    = $this->manager($firstUnitOfWork);
        $nextManager     = $this->manager($nextUnitOfWork);
        $baseRegistry    = $this->createMock(EntityAwareEntityManagerRegistryInterface::class);
        $baseRegistry->expects(self::exactly(2))
            ->method('forConnection')
            ->with('tenant', true)
            ->willReturnOnConsecutiveCalls($firstManager, $nextManager);
        $registry = new TenantEntityManagerRegistry($baseRegistry);
        $entity   = new TenantRuntimeEntity(10);

        $registry->tenant()->unitOfWork()->markManaged($entity);
        self::assertNotNull($runtimeRegistry->node($entity));

        $registry->resetTenant();
        self::assertNull($runtimeRegistry->node($entity));

        $registry->tenant()->unitOfWork()->markManaged($entity);
        self::assertSame($nextUnitOfWork->heap()->node($entity), $runtimeRegistry->node($entity));
    }

    /**
     * Проверяет очистку только подходящих read/write UnitOfWork при адресном reset connection.
     *
     * @see TenantEntityManagerRegistry::reset()
     */
    #[Test]
    public function targetedResetClearsOnlyMatchingConnectionManagers(): void
    {
        $tenantWriteUnitOfWork = $this->unitOfWorkExpectingClear();
        $tenantReadUnitOfWork  = $this->unitOfWorkExpectingClear();
        $analyticsUnitOfWork   = $this->unitOfWorkExpectingNoClear();
        $defaultUnitOfWork     = $this->unitOfWorkExpectingNoClear();
        $baseRegistry          = $this->createMock(EntityAwareEntityManagerRegistryInterface::class);
        $baseRegistry->method('forConnection')->willReturnCallback(
            fn (string $connection, bool $write): EntityManagerInterface => match ([$connection, $write]) {
                ['tenant', true]       => $this->manager($tenantWriteUnitOfWork),
                ['tenant.read', false] => $this->manager($tenantReadUnitOfWork),
                ['analytics', true]    => $this->manager($analyticsUnitOfWork),
            },
        );
        $baseRegistry->method('default')->willReturn($this->manager($defaultUnitOfWork));
        $registry = new TenantEntityManagerRegistry($baseRegistry);

        $registry->forConnection('tenant');
        $registry->forConnection('tenant.read', write: false);
        $registry->forConnection('analytics');
        $registry->default();

        $registry->reset('tenant');
    }

    /**
     * Проверяет очистку UnitOfWork каждого manager при полном reset registry.
     *
     * @see TenantEntityManagerRegistry::reset()
     */
    #[Test]
    public function fullResetClearsEveryCachedManagerUnitOfWork(): void
    {
        $tenantUnitOfWork  = $this->unitOfWorkExpectingClear();
        $defaultUnitOfWork = $this->unitOfWorkExpectingClear();
        $baseRegistry      = $this->createMock(EntityAwareEntityManagerRegistryInterface::class);
        $baseRegistry->method('forConnection')->willReturn($this->manager($tenantUnitOfWork));
        $baseRegistry->method('default')->willReturn($this->manager($defaultUnitOfWork));
        $registry = new TenantEntityManagerRegistry($baseRegistry);

        $registry->tenant();
        $registry->default();

        $registry->reset();
    }

    private function manager(UnitOfWorkInterface $unitOfWork): EntityManagerInterface
    {
        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->method('unitOfWork')->willReturn($unitOfWork);

        return $manager;
    }

    private function unitOfWorkExpectingClear(): UnitOfWorkInterface
    {
        $unitOfWork = $this->createMock(UnitOfWorkInterface::class);
        $unitOfWork->expects(self::once())->method('clear');

        return $unitOfWork;
    }

    private function unitOfWorkExpectingNoClear(): UnitOfWorkInterface
    {
        $unitOfWork = $this->createMock(UnitOfWorkInterface::class);
        $unitOfWork->expects(self::never())->method('clear');

        return $unitOfWork;
    }
}
