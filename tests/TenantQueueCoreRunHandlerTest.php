<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests;

use PhpSoftBox\MultiTenant\Bootstrap\TenantBootstrapPipeline;
use PhpSoftBox\MultiTenant\Cli\TenantQueueCoreRunHandler;
use PhpSoftBox\MultiTenant\Context\InMemoryTenantContextStore;
use PhpSoftBox\MultiTenant\Context\TenantContextFactory;
use PhpSoftBox\MultiTenant\Context\TenantContextResolver;
use PhpSoftBox\MultiTenant\Tenant\Runtime\TenantRuntimeExecutor;
use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;
use PhpSoftBox\MultiTenant\Tenant\TenantSelector;
use PhpSoftBox\MultiTenant\Tests\Support\CliTestRunner;
use PhpSoftBox\MultiTenant\Tests\Support\TestTenantProvider;
use PhpSoftBox\Queue\Drivers\InMemoryDriver;
use PhpSoftBox\Queue\QueueJob;
use PhpSoftBox\Queue\QueueJobHandlerInterface;
use PhpSoftBox\Queue\Worker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Throwable;

#[CoversClass(TenantQueueCoreRunHandler::class)]
#[CoversMethod(TenantQueueCoreRunHandler::class, 'run')]
final class TenantQueueCoreRunHandlerTest extends TestCase
{
    /**
     * Проверим, что задача выполняется в контексте арендатора из tenant_id.
     *
     * @see TenantQueueCoreRunHandler::run()
     */
    #[Test]
    public function jobRunsInTenantFromPayload(): void
    {
        [$handled, $failures] = $this->process('2');

        $this->assertSame(['2'], $handled);
        $this->assertSame([], $failures);
    }

    /**
     * Проверим, что tenant_id `all` не выполняет задачу в контексте первого арендатора, а приводит к ошибке задачи.
     *
     * @see TenantQueueCoreRunHandler::run()
     */
    #[Test]
    public function jobWithAllTenantsSelectorFails(): void
    {
        [$handled, $failures] = $this->process('all');

        $this->assertSame([], $handled);
        $this->assertSame(['Tenant не найден для queue payload: all'], $failures);
    }

    /**
     * Проверим, что tenant_id со списком арендаторов через запятую приводит к ошибке задачи.
     *
     * @see TenantQueueCoreRunHandler::run()
     */
    #[Test]
    public function jobWithTenantListFails(): void
    {
        [$handled, $failures] = $this->process('2,1');

        $this->assertSame([], $handled);
        $this->assertSame(['Tenant не найден для queue payload: 2,1'], $failures);
    }

    /**
     * @return array{0: list<string>, 1: list<string>}
     */
    private function process(string $tenantId): array
    {
        $queue = new InMemoryDriver();

        $queue->push(QueueJob::fromPayload(['tenant_id' => $tenantId, 'payload' => ['task' => 'x']]));

        $failures = [];
        $worker   = new Worker(
            queue: $queue,
            maxAttempts: 1,
            onFailure: static function (QueueJob $job, Throwable $exception) use (&$failures): void {
                $failures[] = $exception->getMessage();
            },
        );

        $store = new InMemoryTenantContextStore();

        $resolver = new TenantContextResolver($store);
        $handled  = [];
        $handler  = new readonly class ($resolver, static function (string $id) use (&$handled): void {
            $handled[] = $id;
        }) implements QueueJobHandlerInterface {
            public function __construct(
                private TenantContextResolver $resolver,
                private mixed $record,
            ) {
            }

            public function handle(mixed $payload, QueueJob $job): void
            {
                ($this->record)($this->resolver->getOrFail()->id);
            }
        };

        $provider = new TestTenantProvider([
            new TenantDefinition('1', 'Tenant 1', null, 'tenant'),
            new TenantDefinition('2', 'Tenant 2', null, 'tenant'),
        ]);

        $command = new TenantQueueCoreRunHandler(
            worker: $worker,
            handler: $handler,
            selector: new TenantSelector($provider),
            runtime: new TenantRuntimeExecutor(new TenantContextFactory(), new TenantBootstrapPipeline(), $store),
        );

        $command->run(new CliTestRunner());

        return [$handled, $failures];
    }
}
