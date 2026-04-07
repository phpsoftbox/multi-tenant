<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests;

use PhpSoftBox\MultiTenant\Orm\TenantEntityManagerRegistry;
use PhpSoftBox\Orm\Contracts\EntityAwareEntityManagerRegistryInterface;
use PhpSoftBox\Orm\Contracts\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TenantEntityManagerRegistry::class)]
#[CoversMethod(TenantEntityManagerRegistry::class, 'resetTenant')]
final class TenantEntityManagerRegistryLifecycleTest extends TestCase
{
    /**
     * Проверяет, что resetTenant использует alias самого registry и создаёт новый manager.
     *
     * @see TenantEntityManagerRegistry::tenant()
     * @see TenantEntityManagerRegistry::resetTenant()
     */
    #[Test]
    public function resetTenantUsesRegistryConnectionName(): void
    {
        $first  = $this->createMock(EntityManagerInterface::class);
        $second = $this->createMock(EntityManagerInterface::class);
        $base   = $this->createMock(EntityAwareEntityManagerRegistryInterface::class);
        $base->expects(self::exactly(2))
            ->method('forConnection')
            ->with('tenant_runtime', true)
            ->willReturnOnConsecutiveCalls($first, $second);
        $registry = new TenantEntityManagerRegistry($base, 'tenant_runtime');

        self::assertSame($first, $registry->tenant());
        $registry->resetTenant();
        self::assertSame($second, $registry->tenant());
    }
}
