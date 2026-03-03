<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests;

use InvalidArgumentException;
use PhpSoftBox\MultiTenant\Contracts\TenantProvisionRunnerInterface;
use PhpSoftBox\MultiTenant\Provision\Queue\TenantProvisionQueueJobHandler;
use PhpSoftBox\MultiTenant\Provision\TenantProvisionContext;
use PhpSoftBox\MultiTenant\Provision\TenantProvisionPayload;
use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TenantProvisionQueueJobHandler::class)]
final class TenantProvisionQueueJobHandlerTest extends TestCase
{
    #[Test]
    public function testSupportsRecognizesProvisionPayload(): void
    {
        $runner  = $this->createMock(TenantProvisionRunnerInterface::class);
        $handler = new TenantProvisionQueueJobHandler($runner);

        $this->assertTrue($handler->supports([
            '_mt_job'          => TenantProvisionPayload::JOB_TYPE,
            'tenant_provision' => ['tenant_id' => 'tenant-1'],
        ]));

        $this->assertFalse($handler->supports([
            '_mt_job' => 'other.job',
        ]));
    }

    #[Test]
    public function testHandlePassesPayloadToRunner(): void
    {
        $runner  = $this->createMock(TenantProvisionRunnerInterface::class);
        $handler = new TenantProvisionQueueJobHandler($runner);

        $runner->expects($this->once())
            ->method('run')
            ->with($this->callback(static function (TenantProvisionPayload $payload): bool {
                return $payload->tenantId === 'tenant-1'
                    && $payload->templateTenantId === 'template-1';
            }))
            ->willReturnCallback(static function (TenantProvisionPayload $payload): TenantProvisionContext {
                $tenant   = new TenantDefinition($payload->tenantId, 'Tenant', null, 'tenant');
                $template = new TenantDefinition('template-1', 'Template', null, 'tenant_template');

                return new TenantProvisionContext($tenant, $template, $payload);
            });

        $handler->handle([
            '_mt_job'          => TenantProvisionPayload::JOB_TYPE,
            'tenant_provision' => [
                'tenant_id'          => 'tenant-1',
                'template_tenant_id' => 'template-1',
            ],
        ]);
    }

    #[Test]
    public function testHandleRejectsNonArrayPayload(): void
    {
        $runner  = $this->createMock(TenantProvisionRunnerInterface::class);
        $handler = new TenantProvisionQueueJobHandler($runner);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Tenant provision payload должен быть массивом.');

        $handler->handle('invalid');
    }

    #[Test]
    public function testHandleRejectsPayloadWithoutTenantProvisionBlock(): void
    {
        $runner  = $this->createMock(TenantProvisionRunnerInterface::class);
        $handler = new TenantProvisionQueueJobHandler($runner);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Tenant provision payload не содержит tenant_provision.');

        $handler->handle([
            '_mt_job' => TenantProvisionPayload::JOB_TYPE,
        ]);
    }
}
