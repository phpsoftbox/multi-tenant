<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests;

use PhpSoftBox\MultiTenant\Orm\TenantEntityManagerRegistry;
use PhpSoftBox\Orm\Contracts\EntityManagerInterface;
use PhpSoftBox\Orm\Contracts\EntityManagerRegistryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TenantEntityManagerRegistry::class)]
final class TenantEntityManagerRegistryTest extends TestCase
{
    #[Test]
    public function defaultCachesEntityManagerByReadWriteMode(): void
    {
        $writeEntityManager = $this->createMock(EntityManagerInterface::class);
        $readEntityManager  = $this->createMock(EntityManagerInterface::class);

        $base = $this->createMock(EntityManagerRegistryInterface::class);
        $base->expects(self::exactly(2))
            ->method('default')
            ->willReturnCallback(static function (bool $write) use ($writeEntityManager, $readEntityManager): EntityManagerInterface {
                return $write ? $writeEntityManager : $readEntityManager;
            });
        $base->expects(self::never())->method('forConnection');

        $registry = new TenantEntityManagerRegistry($base);

        self::assertSame($writeEntityManager, $registry->default());
        self::assertSame($writeEntityManager, $registry->default());
        self::assertSame($readEntityManager, $registry->default(write: false));
        self::assertSame($readEntityManager, $registry->default(write: false));
    }

    #[Test]
    public function tenantUsesConfiguredTenantConnectionName(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);

        $base = $this->createMock(EntityManagerRegistryInterface::class);
        $base->expects(self::once())->method('forConnection')->with('tenant_runtime', true)->willReturn($entityManager);

        $registry = new TenantEntityManagerRegistry(
            registry: $base,
            tenantConnectionName: 'tenant_runtime',
        );

        self::assertSame($entityManager, $registry->tenant());
        self::assertSame($entityManager, $registry->tenant());
    }

    #[Test]
    public function emptyConnectionNameFallsBackToDefault(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);

        $base = $this->createMock(EntityManagerRegistryInterface::class);
        $base->expects(self::once())->method('default')->with(false)->willReturn($entityManager);
        $base->expects(self::never())->method('forConnection');

        $registry = new TenantEntityManagerRegistry($base);

        self::assertSame($entityManager, $registry->forConnection('   ', write: false));
    }

    #[Test]
    public function resetClearsOnlyMatchingConnectionAndPrefixedConnections(): void
    {
        $tenantWrite     = $this->createMock(EntityManagerInterface::class);
        $tenantRead      = $this->createMock(EntityManagerInterface::class);
        $analyticsWrite  = $this->createMock(EntityManagerInterface::class);
        $tenantWriteNext = $this->createMock(EntityManagerInterface::class);
        $tenantReadNext  = $this->createMock(EntityManagerInterface::class);

        $base = $this->createMock(EntityManagerRegistryInterface::class);
        $base->expects(self::exactly(5))
            ->method('forConnection')
            ->willReturnCallback(static function (string $connectionName, bool $write) use (
                $tenantWrite,
                $tenantRead,
                $analyticsWrite,
                $tenantWriteNext,
                $tenantReadNext,
            ): EntityManagerInterface {
                static $tenantWriteCalls = 0;
                static $tenantReadCalls  = 0;

                if ($connectionName === 'tenant' && $write) {
                    $tenantWriteCalls++;

                    return $tenantWriteCalls === 1 ? $tenantWrite : $tenantWriteNext;
                }

                if ($connectionName === 'tenant.read' && !$write) {
                    $tenantReadCalls++;

                    return $tenantReadCalls === 1 ? $tenantRead : $tenantReadNext;
                }

                self::assertSame('analytics', $connectionName);
                self::assertTrue($write);

                return $analyticsWrite;
            });

        $registry = new TenantEntityManagerRegistry($base);

        self::assertSame($tenantWrite, $registry->forConnection('tenant'));
        self::assertSame($tenantRead, $registry->forConnection('tenant.read', write: false));
        self::assertSame($analyticsWrite, $registry->forConnection('analytics'));

        $registry->reset('tenant');

        self::assertSame($tenantWriteNext, $registry->forConnection('tenant'));
        self::assertSame($tenantReadNext, $registry->forConnection('tenant.read', write: false));
        self::assertSame($analyticsWrite, $registry->forConnection('analytics'));
    }

    #[Test]
    public function resetWithoutConnectionClearsAllCachedManagers(): void
    {
        $first  = $this->createMock(EntityManagerInterface::class);
        $second = $this->createMock(EntityManagerInterface::class);

        $base = $this->createMock(EntityManagerRegistryInterface::class);
        $base->expects(self::exactly(2))
            ->method('forConnection')
            ->with('tenant', true)
            ->willReturnOnConsecutiveCalls($first, $second);

        $registry = new TenantEntityManagerRegistry($base);

        self::assertSame($first, $registry->tenant());

        $registry->reset();

        self::assertSame($second, $registry->tenant());
    }
}
