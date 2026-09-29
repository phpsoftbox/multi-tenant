<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests;

use PhpSoftBox\MultiTenant\Bootstrap\TenantBootstrapPipeline;
use PhpSoftBox\MultiTenant\Bootstrap\TenantBootstrapSession;
use PhpSoftBox\MultiTenant\Context\TenantContext;
use PhpSoftBox\MultiTenant\Context\TenantRuntimeScope;
use PhpSoftBox\MultiTenant\Contracts\TenantBootstrapperInterface;
use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function in_array;

#[CoversClass(TenantBootstrapPipeline::class)]
#[CoversMethod(TenantBootstrapPipeline::class, 'begin')]
#[CoversMethod(TenantBootstrapPipeline::class, 'run')]
#[CoversMethod(TenantBootstrapSession::class, 'teardown')]
final class TenantBootstrapPipelineTest extends TestCase
{
    #[Test]
    public function testBeginBootstrapsByPriorityAndTeardownInReverseOrder(): void
    {
        $log = [];

        $pipeline = new TenantBootstrapPipeline([
            $this->bootstrapper('low', 10, [TenantRuntimeScope::Cli], $log),
            $this->bootstrapper('high', 100, [TenantRuntimeScope::Cli], $log),
        ]);

        $session = $pipeline->begin($this->context(), TenantRuntimeScope::Cli);
        $session->teardown();

        $this->assertSame(
            ['bootstrap:high', 'bootstrap:low', 'teardown:low', 'teardown:high'],
            $log,
        );
    }

    #[Test]
    public function testBeginRollsBackWhenBootstrapperFails(): void
    {
        $log = [];

        $pipeline = new TenantBootstrapPipeline([
            $this->bootstrapper('ok', 100, [TenantRuntimeScope::Cli], $log),
            $this->bootstrapper('fail', 10, [TenantRuntimeScope::Cli], $log, failOnBootstrap: true),
        ]);

        try {
            $pipeline->begin($this->context(), TenantRuntimeScope::Cli);
            $this->fail('Ожидалось исключение bootstrapper.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Bootstrap error: fail', $exception->getMessage());
        }

        $this->assertSame(
            ['bootstrap:ok', 'bootstrap:fail', 'teardown:ok'],
            $log,
        );
    }

    #[Test]
    public function testRunAlwaysCallsTeardown(): void
    {
        $log = [];

        $pipeline = new TenantBootstrapPipeline([
            $this->bootstrapper('one', 10, [TenantRuntimeScope::Cli], $log),
        ]);

        try {
            $pipeline->run(
                $this->context(),
                static function (): void {
                    throw new RuntimeException('Callback failed');
                },
                TenantRuntimeScope::Cli,
            );
            $this->fail('Ожидалось исключение callback.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Callback failed', $exception->getMessage());
        }

        $this->assertSame(['bootstrap:one', 'teardown:one'], $log);
    }

    #[Test]
    public function testSessionTeardownIsIdempotent(): void
    {
        $log = [];

        $pipeline = new TenantBootstrapPipeline([
            $this->bootstrapper('one', 10, [TenantRuntimeScope::Cli], $log),
        ]);

        $session = $pipeline->begin($this->context(), TenantRuntimeScope::Cli);

        $session->teardown();
        $session->teardown();

        $this->assertSame(['bootstrap:one', 'teardown:one'], $log);
    }

    /**
     * Проверим, что ошибка teardown одного bootstrapper-а не мешает откатить остальные (иначе состояние арендатора
     * остаётся в долгоживущем процессе), а после обхода бросается первое исключение.
     *
     * @see TenantBootstrapSession::teardown()
     */
    #[Test]
    public function sessionTeardownRollsBackAllBootstrappersWhenOneFails(): void
    {
        $log = [];

        $pipeline = new TenantBootstrapPipeline([
            $this->bootstrapper('database', 100, [TenantRuntimeScope::Cli], $log),
            $this->bootstrapper('cache', 10, [TenantRuntimeScope::Cli], $log, failOnTeardown: true),
        ]);

        $session = $pipeline->begin($this->context(), TenantRuntimeScope::Cli);

        try {
            $session->teardown();
            $this->fail('Ожидалось исключение teardown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Teardown error: cache', $exception->getMessage());
        }

        // Подключение к БД откачено, несмотря на ошибку отката кеша.
        $this->assertSame(['bootstrap:database', 'bootstrap:cache', 'teardown:cache', 'teardown:database'], $log);
    }

    /**
     * Проверим, что при ошибке callback и ошибке teardown наружу выходит исключение callback, а все bootstrapper-ы
     * откачены.
     *
     * @see TenantBootstrapPipeline::run()
     */
    #[Test]
    public function runKeepsCallbackExceptionWhenTeardownFails(): void
    {
        $log = [];

        $pipeline = new TenantBootstrapPipeline([
            $this->bootstrapper('database', 100, [TenantRuntimeScope::Cli], $log),
            $this->bootstrapper('cache', 10, [TenantRuntimeScope::Cli], $log, failOnTeardown: true),
        ]);

        try {
            $pipeline->run(
                $this->context(),
                static function (): void {
                    throw new RuntimeException('Callback failed');
                },
                TenantRuntimeScope::Cli,
            );
            $this->fail('Ожидалось исключение callback.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Callback failed', $exception->getMessage());
        }

        $this->assertSame(['bootstrap:database', 'bootstrap:cache', 'teardown:cache', 'teardown:database'], $log);
    }

    /**
     * Проверим, что при ошибке bootstrap откат продолжается, даже если откат одного из применённых bootstrapper-ов
     * упал, а наружу выходит исходная ошибка bootstrap.
     *
     * @see TenantBootstrapPipeline::begin()
     */
    #[Test]
    public function beginRollbackContinuesWhenRollbackFails(): void
    {
        $log = [];

        $pipeline = new TenantBootstrapPipeline([
            $this->bootstrapper('database', 100, [TenantRuntimeScope::Cli], $log),
            $this->bootstrapper('cache', 50, [TenantRuntimeScope::Cli], $log, failOnTeardown: true),
            $this->bootstrapper('fail', 10, [TenantRuntimeScope::Cli], $log, failOnBootstrap: true),
        ]);

        try {
            $pipeline->begin($this->context(), TenantRuntimeScope::Cli);
            $this->fail('Ожидалось исключение bootstrapper.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Bootstrap error: fail', $exception->getMessage());
        }

        $this->assertSame(
            ['bootstrap:database', 'bootstrap:cache', 'bootstrap:fail', 'teardown:cache', 'teardown:database'],
            $log,
        );
    }

    /**
     * @param list<TenantRuntimeScope> $supports
     * @param list<string> $log
     */
    private function bootstrapper(
        string $name,
        int $priority,
        array $supports,
        array &$log,
        bool $failOnBootstrap = false,
        bool $failOnTeardown = false,
    ): TenantBootstrapperInterface {
        $append = static function (string $line) use (&$log): void {
            $log[] = $line;
        };

        return new readonly class ($name, $priority, $supports, $append, $failOnBootstrap, $failOnTeardown) implements TenantBootstrapperInterface {
            /**
             * @param list<TenantRuntimeScope> $supports
             */
            public function __construct(
                private string $name,
                private int $priority,
                private array $supports,
                private mixed $append,
                private bool $failOnBootstrap,
                private bool $failOnTeardown,
            ) {
            }

            public function supports(TenantRuntimeScope $scope): bool
            {
                return in_array($scope, $this->supports, true);
            }

            public function priority(): int
            {
                return $this->priority;
            }

            public function bootstrap(TenantContext $context): void
            {
                ($this->append)('bootstrap:' . $this->name);

                if ($this->failOnBootstrap) {
                    throw new RuntimeException('Bootstrap error: ' . $this->name);
                }
            }

            public function teardown(TenantContext $context): void
            {
                ($this->append)('teardown:' . $this->name);

                if ($this->failOnTeardown) {
                    throw new RuntimeException('Teardown error: ' . $this->name);
                }
            }
        };
    }

    private function context(): TenantContext
    {
        return new TenantContext(new TenantDefinition('tenant-1', 'Tenant 1', null, 'tenant_1'));
    }
}
