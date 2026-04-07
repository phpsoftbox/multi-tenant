<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests;

use PhpSoftBox\Config\Config;
use PhpSoftBox\Database\Contracts\ConnectionInterface;
use PhpSoftBox\MultiTenant\Bootstrap\DatabaseTenantConnectionBootstrapper;
use PhpSoftBox\MultiTenant\Bootstrap\TenantBootstrapPipeline;
use PhpSoftBox\MultiTenant\Bootstrap\TenantOrmEntityManagerBootstrapper;
use PhpSoftBox\MultiTenant\Context\InMemoryTenantContextStore;
use PhpSoftBox\MultiTenant\Context\TenantContextFactory;
use PhpSoftBox\MultiTenant\Database\TenantDsnResolver;
use PhpSoftBox\MultiTenant\Orm\TenantEntityManagerRegistry;
use PhpSoftBox\MultiTenant\Tenant\Runtime\TenantRuntimeExecutor;
use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;
use PhpSoftBox\MultiTenant\Tests\Support\StackTenantConnectionSwitcher;
use PhpSoftBox\Orm\Contracts\EntityAwareEntityManagerRegistryInterface;
use PhpSoftBox\Orm\Contracts\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(TenantOrmEntityManagerBootstrapper::class)]
#[CoversClass(TenantEntityManagerRegistry::class)]
#[CoversClass(TenantRuntimeExecutor::class)]
#[CoversMethod(TenantRuntimeExecutor::class, 'run')]
#[CoversMethod(TenantEntityManagerRegistry::class, 'tenant')]
#[CoversMethod(TenantEntityManagerRegistry::class, 'resetTenant')]
final class TenantOrmRuntimeLifecycleTest extends TestCase
{
    /**
     * Проверяет получение разных tenant EntityManager в двух последовательных scope долгоживущего процесса.
     *
     * @see TenantRuntimeExecutor::run()
     * @see TenantEntityManagerRegistry::tenant()
     * @see TenantEntityManagerRegistry::resetTenant()
     */
    #[Test]
    public function consecutiveScopesForSameTenantUseDifferentManagers(): void
    {
        $base     = $this->baseRegistryCreatingManagers();
        $switcher = new StackTenantConnectionSwitcher();

        [$runtime, $registry] = $this->runtime($base, $switcher);
        $tenant               = $this->tenant('tenant-a');

        $first  = $runtime->run($tenant, static fn (): EntityManagerInterface => $registry->tenant());
        $second = $runtime->run($tenant, static fn (): EntityManagerInterface => $registry->tenant());

        self::assertNotSame($first, $second);
        self::assertNull($switcher->activeDsn());
    }

    /**
     * Проверяет отсутствие повторного использования manager при последовательном переключении tenant A → tenant B.
     *
     * @see TenantRuntimeExecutor::run()
     * @see TenantEntityManagerRegistry::tenant()
     */
    #[Test]
    public function sequentialTenantSwitchCreatesManagerForEachDsn(): void
    {
        $switcher = new StackTenantConnectionSwitcher();
        $created  = [];
        $base     = $this->createMock(EntityAwareEntityManagerRegistryInterface::class);
        $base->expects(self::exactly(2))
            ->method('forConnection')
            ->willReturnCallback(function () use (&$created, $switcher): EntityManagerInterface {
                $manager   = $this->createMock(EntityManagerInterface::class);
                $created[] = [$manager, $switcher->activeDsn()];

                return $manager;
            });
        [$runtime, $registry] = $this->runtime($base, $switcher);

        $runtime->run(
            $this->tenant('tenant-a'),
            static fn (): EntityManagerInterface => $registry->tenant(),
        );
        $runtime->run(
            $this->tenant('tenant-b'),
            static fn (): EntityManagerInterface => $registry->tenant(),
        );

        self::assertNotSame($created[0][0], $created[1][0]);
        self::assertSame('test://tenant-a', $created[0][1]);
        self::assertSame('test://tenant-b', $created[1][1]);
    }

    /**
     * Проверяет восстановление tenant A и создание нового manager после вложенного scope tenant B.
     *
     * @see TenantRuntimeExecutor::run()
     * @see TenantEntityManagerRegistry::resetTenant()
     */
    #[Test]
    public function nestedTenantScopeRecreatesOuterManagerWithRestoredDsn(): void
    {
        $switcher = new StackTenantConnectionSwitcher();
        $created  = [];
        $base     = $this->createMock(EntityAwareEntityManagerRegistryInterface::class);
        $base->expects(self::exactly(3))
            ->method('forConnection')
            ->willReturnCallback(function () use (&$created, $switcher): EntityManagerInterface {
                $manager   = $this->createMock(EntityManagerInterface::class);
                $created[] = [$manager, $switcher->activeDsn()];

                return $manager;
            });
        [$runtime, $registry] = $this->runtime($base, $switcher);
        $outerBefore          = null;
        $inner                = null;
        $outerAfter           = null;

        $runtime->run($this->tenant('tenant-a'), function () use (
            $runtime,
            $registry,
            &$outerBefore,
            &$inner,
            &$outerAfter,
        ): void {
            $outerBefore = $registry->tenant();
            $inner       = $runtime->run(
                $this->tenant('tenant-b'),
                static fn (): EntityManagerInterface => $registry->tenant(),
            );
            $outerAfter = $registry->tenant();
        });

        self::assertNotSame($outerBefore, $inner);
        self::assertNotSame($outerBefore, $outerAfter);
        self::assertNotSame($inner, $outerAfter);
        self::assertSame(
            ['test://tenant-a', 'test://tenant-b', 'test://tenant-a'],
            [$created[0][1], $created[1][1], $created[2][1]],
        );
        self::assertNull($switcher->activeDsn());
    }

    /**
     * Проверяет сброс manager после исключения callback и повторное выбрасывание исходной ошибки executor-ом.
     *
     * @see TenantRuntimeExecutor::run()
     * @see TenantOrmEntityManagerBootstrapper::teardown()
     */
    #[Test]
    public function callbackExceptionDoesNotKeepManagerForNextScope(): void
    {
        $base     = $this->baseRegistryCreatingManagers();
        $switcher = new StackTenantConnectionSwitcher();

        [$runtime, $registry] = $this->runtime($base, $switcher);
        $first                = null;

        try {
            $runtime->run($this->tenant('tenant-a'), static function () use ($registry, &$first): never {
                $first = $registry->tenant();

                throw new RuntimeException('Tenant callback failed.');
            });
            self::fail('Tenant callback exception was expected.');
        } catch (RuntimeException $exception) {
            self::assertSame('Tenant callback failed.', $exception->getMessage());
        }

        $second = $runtime->run(
            $this->tenant('tenant-a'),
            static fn (): EntityManagerInterface => $registry->tenant(),
        );

        self::assertNotSame($first, $second);
        self::assertNull($switcher->activeDsn());
    }

    /**
     * Проверяет сохранение core/default EntityManager при сбросах tenant lifecycle.
     *
     * @see TenantEntityManagerRegistry::default()
     * @see TenantEntityManagerRegistry::resetTenant()
     */
    #[Test]
    public function tenantLifecycleDoesNotResetDefaultManager(): void
    {
        $core = $this->createMock(EntityManagerInterface::class);
        $base = $this->createMock(EntityAwareEntityManagerRegistryInterface::class);
        $base->expects(self::once())->method('default')->with(true)->willReturn($core);
        $base->method('forConnection')->willReturnCallback(
            fn (): EntityManagerInterface => $this->createMock(EntityManagerInterface::class),
        );
        $switcher = new StackTenantConnectionSwitcher();

        [$runtime, $registry] = $this->runtime($base, $switcher);

        $before = $registry->default();
        $inside = $runtime->run($this->tenant('tenant-a'), static function () use ($registry): EntityManagerInterface {
            $registry->tenant();

            return $registry->default();
        });
        $after = $registry->default();

        self::assertSame($core, $before);
        self::assertSame($before, $inside);
        self::assertSame($inside, $after);
    }

    /**
     * Проверяет, что manager с имитацией закрытого соединения не используется в следующем worker scope.
     *
     * @see TenantRuntimeExecutor::run()
     * @see TenantEntityManagerRegistry::resetTenant()
     */
    #[Test]
    public function nextScopeDoesNotReuseManagerWithStaleConnection(): void
    {
        $staleManager = $this->createMock(EntityManagerInterface::class);
        $staleManager->method('connection')->willThrowException(new RuntimeException('server has gone away'));
        $freshConnection = $this->createMock(ConnectionInterface::class);
        $freshManager    = $this->createMock(EntityManagerInterface::class);
        $freshManager->expects(self::once())->method('connection')->willReturn($freshConnection);
        $base = $this->createMock(EntityAwareEntityManagerRegistryInterface::class);
        $base->expects(self::exactly(2))
            ->method('forConnection')
            ->willReturnOnConsecutiveCalls($staleManager, $freshManager);
        $switcher = new StackTenantConnectionSwitcher();

        [$runtime, $registry] = $this->runtime($base, $switcher);

        $first = $runtime->run(
            $this->tenant('tenant-a'),
            static fn (): EntityManagerInterface => $registry->tenant(),
        );
        $connection = $runtime->run(
            $this->tenant('tenant-a'),
            static fn (): ConnectionInterface => $registry->tenant()->connection(),
        );

        self::assertSame($staleManager, $first);
        self::assertSame($freshConnection, $connection);
    }

    /**
     * @return array{TenantRuntimeExecutor,TenantEntityManagerRegistry}
     */
    private function runtime(
        EntityAwareEntityManagerRegistryInterface $base,
        StackTenantConnectionSwitcher $switcher,
    ): array {
        $registry             = new TenantEntityManagerRegistry($base);
        $databaseBootstrapper = new DatabaseTenantConnectionBootstrapper(
            $switcher,
            new TenantDsnResolver(new Config()),
        );
        $ormBootstrapper = new TenantOrmEntityManagerBootstrapper($registry);

        $pipeline = new TenantBootstrapPipeline([$databaseBootstrapper, $ormBootstrapper]);

        $runtime = new TenantRuntimeExecutor(
            new TenantContextFactory(),
            $pipeline,
            new InMemoryTenantContextStore(),
        );

        return [$runtime, $registry];
    }

    /**
     * @return EntityAwareEntityManagerRegistryInterface&MockObject
     */
    private function baseRegistryCreatingManagers(): EntityAwareEntityManagerRegistryInterface
    {
        $base = $this->createMock(EntityAwareEntityManagerRegistryInterface::class);
        $base->method('forConnection')->willReturnCallback(
            fn (): EntityManagerInterface => $this->createMock(EntityManagerInterface::class),
        );

        return $base;
    }

    private function tenant(string $id): TenantDefinition
    {
        return new TenantDefinition(
            id: $id,
            name: $id,
            host: null,
            databaseConnection: 'tenant',
            data: ['dsn' => 'test://' . $id],
        );
    }
}
