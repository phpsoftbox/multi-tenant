<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests;

use PhpSoftBox\Config\Config;
use PhpSoftBox\MultiTenant\Provision\Step\CliCommandListProvisionStep;
use PhpSoftBox\MultiTenant\Provision\TenantProvisionContext;
use PhpSoftBox\MultiTenant\Provision\TenantProvisionPayload;
use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;
use PhpSoftBox\MultiTenant\Tests\Support\RecordingProvisionCommandRunner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(CliCommandListProvisionStep::class)]
#[CoversMethod(CliCommandListProvisionStep::class, 'run')]
final class CliCommandListProvisionStepTest extends TestCase
{
    /**
     * Проверим, что значения из payload подставляются целыми аргументами: кавычки и операторы shell в имени владельца
     * не разбивают аргумент и не запускают других команд.
     *
     * @see CliCommandListProvisionStep::run()
     */
    #[Test]
    public function payloadValuesBecomeSingleArguments(): void
    {
        $runner = new RecordingProvisionCommandRunner();

        $step = new CliCommandListProvisionStep($runner, new Config([[
                    'tenancy' => [
                        'provision' => [
                            'commands' => [
                                'tenant:user:create {owner_phone} --tenant={tenant_id} --name="{owner_name}" --email=\'{owner_email}\'',
                            ],
                        ],
                    ],
                ]]));

        $step->run($this->context(new TenantProvisionPayload(
            tenantId: '5',
            ownerPhone: '79990000000',
            ownerName: 'O\'Brien"; rm -rf / #$(id)',
            ownerEmail: 'owner@example.test',
        )));

        $this->assertSame(
            [[
                'tenant:user:create',
                '79990000000',
                '--tenant=5',
                '--name=O\'Brien"; rm -rf / #$(id)',
                '--email=owner@example.test',
            ]],
            $runner->commands,
        );
    }

    /**
     * Проверим, что неизвестный placeholder в шаблоне команды приводит к ошибке до запуска команды.
     *
     * @see CliCommandListProvisionStep::run()
     */
    #[Test]
    public function unknownPlaceholderFails(): void
    {
        $runner = new RecordingProvisionCommandRunner();

        $step = new CliCommandListProvisionStep($runner, new Config([[
                    'tenancy' => ['provision' => ['commands' => ['tenant:user:create --tenant={tenant_uuid}']]],
                ]]));

        try {
            $step->run($this->context(new TenantProvisionPayload(tenantId: '5')));
            $this->fail('Ожидалась ошибка неизвестного placeholder.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('{tenant_uuid}', $exception->getMessage());
        }

        $this->assertSame([], $runner->commands);
    }

    /**
     * Проверим, что фигурные скобки в значении из payload не считаются неразрешённым placeholder.
     *
     * @see CliCommandListProvisionStep::run()
     */
    #[Test]
    public function bracesInPayloadValueAreKept(): void
    {
        $runner = new RecordingProvisionCommandRunner();

        $step = new CliCommandListProvisionStep($runner, new Config([[
                    'tenancy' => ['provision' => ['commands' => ['tenant:user:create --name={owner_name}']]],
                ]]));

        $step->run($this->context(new TenantProvisionPayload(tenantId: '5', ownerName: '{tenant_id}')));

        $this->assertSame([['tenant:user:create', '--name={tenant_id}']], $runner->commands);
    }

    private function context(TenantProvisionPayload $payload): TenantProvisionContext
    {
        return new TenantProvisionContext(
            tenant: new TenantDefinition('5', 'Tenant 5', null, 'tenant'),
            templateTenant: new TenantDefinition('template', 'Template', null, 'tenant_template'),
            payload: $payload,
        );
    }
}
