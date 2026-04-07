<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests;

use PhpSoftBox\Config\Config;
use PhpSoftBox\Database\Configurator\DatabaseFactory;
use PhpSoftBox\Database\Connection\ConnectionManager;
use PhpSoftBox\Http\Message\ResponseFactory;
use PhpSoftBox\Http\Message\ServerRequest;
use PhpSoftBox\MultiTenant\Bootstrap\TenantBootstrapPipeline;
use PhpSoftBox\MultiTenant\Context\InMemoryTenantContextStore;
use PhpSoftBox\MultiTenant\Context\TenantContext;
use PhpSoftBox\MultiTenant\Context\TenantContextFactory;
use PhpSoftBox\MultiTenant\Context\TenantRuntimeScope;
use PhpSoftBox\MultiTenant\Contracts\TenantBootstrapperInterface;
use PhpSoftBox\MultiTenant\Database\TenantAwareConnectionManager;
use PhpSoftBox\MultiTenant\Http\Middleware\TenantResolveMiddleware;
use PhpSoftBox\MultiTenant\Profiler\MultiTenantProfilerCollector;
use PhpSoftBox\MultiTenant\Tenant\Provider\ConfigTenantProvider;
use PhpSoftBox\MultiTenant\Tenant\Runtime\TenantRuntimeExecutor;
use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;
use PhpSoftBox\Profiler\Profiler;
use PhpSoftBox\Profiler\ProfileTrace;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Вне активной трассы коллектор никто не прочитает и не очистит, поэтому места записи событий
 * должны писать в него только при включённом профайлере и открытой трассе.
 */
#[CoversClass(TenantAwareConnectionManager::class)]
#[CoversClass(TenantBootstrapPipeline::class)]
#[CoversClass(TenantResolveMiddleware::class)]
#[CoversClass(TenantRuntimeExecutor::class)]
final class ProfilerCollectorOutsideTraceTest extends TestCase
{
    /**
     * Проверим, что при выключенном профайлере события tenant-соединения не записываются.
     *
     * @see TenantAwareConnectionManager::activate()
     */
    #[Test]
    public function connectionManagerSkipsEventsWhenProfilerDisabled(): void
    {
        $collector = new MultiTenantProfilerCollector();

        $this->activateConnection(new Profiler(enabled: false), $collector);

        self::assertSame(0, $this->events($collector));
    }

    /**
     * Проверим, что включённый профайлер без трассы тоже не даёт записывать события.
     *
     * @see TenantAwareConnectionManager::activate()
     */
    #[Test]
    public function connectionManagerSkipsEventsWithoutTrace(): void
    {
        $collector = new MultiTenantProfilerCollector();

        $this->activateConnection(new Profiler(), $collector);

        self::assertSame(0, $this->events($collector));
    }

    /**
     * Проверим, что внутри трассы события tenant-соединения записываются.
     *
     * @see TenantAwareConnectionManager::activate()
     */
    #[Test]
    public function connectionManagerRecordsEventsInsideTrace(): void
    {
        $collector = new MultiTenantProfilerCollector();
        $profiler  = new Profiler();

        $profiler->startTrace('test.cli', 'cli');

        $this->activateConnection($profiler, $collector);

        self::assertGreaterThan(0, $this->events($collector));
    }

    /**
     * Проверим, что при выключенном профайлере события bootstrap не записываются.
     *
     * @see TenantBootstrapPipeline::run()
     */
    #[Test]
    public function bootstrapPipelineSkipsEventsWhenProfilerDisabled(): void
    {
        $collector = new MultiTenantProfilerCollector();

        $this->runPipeline(new Profiler(enabled: false), $collector);

        self::assertSame(0, $this->events($collector));
    }

    /**
     * Проверим, что внутри трассы события bootstrap записываются.
     *
     * @see TenantBootstrapPipeline::run()
     */
    #[Test]
    public function bootstrapPipelineRecordsEventsInsideTrace(): void
    {
        $collector = new MultiTenantProfilerCollector();
        $profiler  = new Profiler();

        $profiler->startTrace('test.cli', 'cli');

        $this->runPipeline($profiler, $collector);

        self::assertGreaterThan(0, $this->events($collector));
    }

    /**
     * Проверим, что при выключенном профайлере событие tenant runtime не записывается.
     *
     * @see TenantRuntimeExecutor::run()
     */
    #[Test]
    public function runtimeExecutorSkipsEventsWhenProfilerDisabled(): void
    {
        $collector = new MultiTenantProfilerCollector();

        $this->runRuntime(new Profiler(enabled: false), $collector);

        self::assertSame(0, $this->events($collector));
    }

    /**
     * Проверим, что внутри трассы событие tenant runtime записывается.
     *
     * @see TenantRuntimeExecutor::run()
     */
    #[Test]
    public function runtimeExecutorRecordsEventsInsideTrace(): void
    {
        $collector = new MultiTenantProfilerCollector();
        $profiler  = new Profiler();

        $profiler->startTrace('test.cli', 'cli');

        $this->runRuntime($profiler, $collector);

        self::assertGreaterThan(0, $this->events($collector));
    }

    /**
     * Проверим, что при выключенном профайлере события резолва tenant в HTTP не записываются.
     *
     * @see TenantResolveMiddleware::process()
     */
    #[Test]
    public function resolveMiddlewareSkipsEventsWhenProfilerDisabled(): void
    {
        $collector = new MultiTenantProfilerCollector();

        $this->resolveTenant(new Profiler(enabled: false), $collector);

        self::assertSame(0, $this->events($collector));
    }

    /**
     * Проверим, что внутри трассы события резолва tenant в HTTP записываются.
     *
     * @see TenantResolveMiddleware::process()
     */
    #[Test]
    public function resolveMiddlewareRecordsEventsInsideTrace(): void
    {
        $collector = new MultiTenantProfilerCollector();
        $profiler  = new Profiler();

        $profiler->startTrace('test.request', 'http');

        $this->resolveTenant($profiler, $collector);

        self::assertGreaterThan(0, $this->events($collector));
    }

    private function activateConnection(Profiler $profiler, MultiTenantProfilerCollector $collector): void
    {
        $databaseConfig = [
            'connections' => [
                'default' => 'tenant',
                'tenant'  => ['dsn' => 'sqlite:///:memory:'],
            ],
        ];

        $manager = new TenantAwareConnectionManager(
            baseManager: new ConnectionManager(new DatabaseFactory($databaseConfig)),
            config: new Config([['database' => $databaseConfig]]),
            profiler: $profiler,
            profilerCollector: $collector,
        );

        $manager->activate('sqlite:///:memory:');
        $manager->connection('tenant');
        $manager->deactivate();
    }

    private function runPipeline(Profiler $profiler, MultiTenantProfilerCollector $collector): void
    {
        // Bootstrapper не поддерживает CLI: конвейер пишет для него событие tenant.bootstrap.skip.
        $httpOnly = new class () implements TenantBootstrapperInterface {
            public function supports(TenantRuntimeScope $scope): bool
            {
                return $scope === TenantRuntimeScope::Http;
            }

            public function priority(): int
            {
                return 0;
            }

            public function bootstrap(TenantContext $context): void
            {
            }

            public function teardown(TenantContext $context): void
            {
            }
        };

        $pipeline = new TenantBootstrapPipeline([$httpOnly], $profiler, $collector);

        $pipeline->run($this->context(), static fn (): null => null, TenantRuntimeScope::Cli);
    }

    private function runRuntime(Profiler $profiler, MultiTenantProfilerCollector $collector): void
    {
        $executor = new TenantRuntimeExecutor(
            contextFactory: new TenantContextFactory(),
            pipeline: new TenantBootstrapPipeline(),
            contextStore: new InMemoryTenantContextStore(),
            profiler: $profiler,
            profilerCollector: $collector,
        );

        $executor->run($this->context()->tenant(), static fn (): null => null);
    }

    private function resolveTenant(Profiler $profiler, MultiTenantProfilerCollector $collector): void
    {
        $provider = new ConfigTenantProvider(new Config([[
            'tenancy' => [
                'tenants' => [
                    'tenant-1' => [
                        'name'                => 'Tenant 1',
                        'database_connection' => 'tenant',
                        'domains'             => [['domain' => 'tenant1.example.test', 'is_primary' => true]],
                        'enabled'             => true,
                    ],
                ],
            ],
        ]]));

        $middleware = new TenantResolveMiddleware(
            tenants: $provider,
            tenantRuntime: new TenantRuntimeExecutor(
                contextFactory: new TenantContextFactory(),
                pipeline: new TenantBootstrapPipeline(),
                contextStore: new InMemoryTenantContextStore(),
            ),
            profiler: $profiler,
            profilerCollector: $collector,
        );

        $handler = new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new ResponseFactory()->createResponse(204);
            }
        };

        $middleware->process(new ServerRequest('GET', 'https://tenant1.example.test/'), $handler);
    }

    private function context(): TenantContext
    {
        return new TenantContext(new TenantDefinition('tenant-1', 'Tenant 1', null, 'tenant'));
    }

    private function events(MultiTenantProfilerCollector $collector): int
    {
        return $collector->collect(new ProfileTrace('id', 'test', 'test'))['summary']['events'];
    }
}
