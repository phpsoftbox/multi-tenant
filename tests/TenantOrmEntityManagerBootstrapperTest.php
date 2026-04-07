<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests;

use PhpSoftBox\MultiTenant\Bootstrap\TenantOrmEntityManagerBootstrapper;
use PhpSoftBox\MultiTenant\Context\TenantContext;
use PhpSoftBox\MultiTenant\Context\TenantRuntimeScope;
use PhpSoftBox\MultiTenant\Orm\TenantEntityManagerRegistryInterface;
use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TenantOrmEntityManagerBootstrapper::class)]
#[CoversMethod(TenantOrmEntityManagerBootstrapper::class, 'supports')]
#[CoversMethod(TenantOrmEntityManagerBootstrapper::class, 'priority')]
#[CoversMethod(TenantOrmEntityManagerBootstrapper::class, 'bootstrap')]
#[CoversMethod(TenantOrmEntityManagerBootstrapper::class, 'teardown')]
final class TenantOrmEntityManagerBootstrapperTest extends TestCase
{
    /**
     * Проверяет поддержку bootstrapper-ом каждого объявленного runtime scope, включая WebSocket.
     *
     * @see TenantOrmEntityManagerBootstrapper::supports()
     * @see TenantRuntimeScope::cases()
     */
    #[Test]
    public function supportsEveryRuntimeScope(): void
    {
        $bootstrapper = new TenantOrmEntityManagerBootstrapper(
            $this->createMock(TenantEntityManagerRegistryInterface::class),
        );

        foreach (TenantRuntimeScope::cases() as $scope) {
            self::assertTrue($bootstrapper->supports($scope), $scope->value);
        }
    }

    /**
     * Проверяет, что стандартный priority размещает ORM reset сразу после активации tenant DSN.
     *
     * @see TenantOrmEntityManagerBootstrapper::priority()
     */
    #[Test]
    public function hasPriorityImmediatelyBelowDatabaseBootstrapper(): void
    {
        $bootstrapper = new TenantOrmEntityManagerBootstrapper(
            $this->createMock(TenantEntityManagerRegistryInterface::class),
        );

        self::assertSame(950, $bootstrapper->priority());
    }

    /**
     * Проверяет сброс tenant EntityManager на обеих границах runtime scope.
     *
     * @see TenantOrmEntityManagerBootstrapper::bootstrap()
     * @see TenantOrmEntityManagerBootstrapper::teardown()
     * @see TenantEntityManagerRegistryInterface::resetTenant()
     */
    #[Test]
    public function resetsTenantManagerDuringBootstrapAndTeardown(): void
    {
        $registry = $this->createMock(TenantEntityManagerRegistryInterface::class);
        $registry->expects(self::exactly(2))->method('resetTenant');
        $bootstrapper = new TenantOrmEntityManagerBootstrapper($registry);
        $context      = $this->context();

        $bootstrapper->bootstrap($context);
        $bootstrapper->teardown($context);
    }

    private function context(): TenantContext
    {
        return new TenantContext(new TenantDefinition(
            id: 'tenant-a',
            name: 'Tenant A',
            host: null,
            databaseConnection: 'tenant',
        ));
    }
}
