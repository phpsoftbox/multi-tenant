<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests;

use PhpSoftBox\CliApp\Response;
use PhpSoftBox\MultiTenant\Cli\TenantProvisionDispatchHandler;
use PhpSoftBox\MultiTenant\Provision\Queue\TenantProvisionQueueDispatcher;
use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;
use PhpSoftBox\MultiTenant\Tenant\TenantSelector;
use PhpSoftBox\MultiTenant\Tests\Support\CliTestRunner;
use PhpSoftBox\MultiTenant\Tests\Support\TestTenantProvider;
use PhpSoftBox\Queue\Drivers\InMemoryDriver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function is_array;

#[CoversClass(TenantProvisionDispatchHandler::class)]
#[CoversMethod(TenantProvisionDispatchHandler::class, 'run')]
final class TenantProvisionDispatchHandlerTest extends TestCase
{
    /**
     * Проверим, что `--tenant=all` ставит задачи provisioning для всех арендаторов, включая отключённых.
     *
     * @see TenantProvisionDispatchHandler::run()
     */
    #[Test]
    public function allTenantsIncludeDisabled(): void
    {
        $queue = new InMemoryDriver();

        $handler = new TenantProvisionDispatchHandler(
            selector: new TenantSelector(new TestTenantProvider([
                new TenantDefinition('enabled-tenant', 'Enabled', null, 'tenant'),
                new TenantDefinition('disabled-tenant', 'Disabled', null, 'tenant', enabled: false),
            ])),
            dispatcher: new TenantProvisionQueueDispatcher($queue),
        );

        $result = $handler->run(new CliTestRunner(options: ['tenant' => 'all', 'template' => 'template']));

        $this->assertSame(Response::SUCCESS, $result);

        // Собираем арендаторов из поставленных задач.
        $tenantIds = [];
        while (($job = $queue->pop()) !== null) {
            $payload = $job->payload();
            if (is_array($payload)) {
                $tenantIds[] = $payload['tenant_id'] ?? null;
            }
        }

        $this->assertSame(['enabled-tenant', 'disabled-tenant'], $tenantIds);
    }
}
