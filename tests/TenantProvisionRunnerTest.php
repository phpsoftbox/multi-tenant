<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests;

use PhpSoftBox\Config\Config;
use PhpSoftBox\MultiTenant\Provision\TenantProvisionPayload;
use PhpSoftBox\MultiTenant\Provision\TenantProvisionPipelineFactory;
use PhpSoftBox\MultiTenant\Provision\TenantProvisionRunner;
use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;
use PhpSoftBox\MultiTenant\Tenant\TenantSelector;
use PhpSoftBox\MultiTenant\Tests\Support\TestTenantProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

#[CoversClass(TenantProvisionRunner::class)]
#[CoversMethod(TenantProvisionRunner::class, 'run')]
final class TenantProvisionRunnerTest extends TestCase
{
    /**
     * Проверим, что provisioning выполняется и для отключённого арендатора: его готовят до включения.
     *
     * @see TenantProvisionRunner::run()
     */
    #[Test]
    public function provisionsDisabledTargetTenant(): void
    {
        $target   = new TenantDefinition('new-tenant', 'New tenant', null, 'tenant', enabled: false);
        $template = new TenantDefinition('template', 'Template', null, 'tenant');

        // Пустой список шагов: проверяется только выбор target и template.
        $config = new Config([[
            'tenancy' => [
                'provision' => [
                    'steps' => [],
                ],
            ],
        ]]);

        $runner = new TenantProvisionRunner(
            selector: new TenantSelector(new TestTenantProvider([$target, $template])),
            config: $config,
            pipelineFactory: new TenantProvisionPipelineFactory($config, $this->createStub(ContainerInterface::class)),
        );

        $context = $runner->run(new TenantProvisionPayload(tenantId: 'new-tenant', templateTenantId: 'template'));

        $this->assertSame($target, $context->tenant());
        $this->assertSame($template, $context->templateTenant());
    }
}
