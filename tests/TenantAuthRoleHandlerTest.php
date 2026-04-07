<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests;

use PhpSoftBox\Auth\Authorization\RoleSyncPlan;
use PhpSoftBox\CliApp\Response;
use PhpSoftBox\MultiTenant\Bootstrap\TenantBootstrapPipeline;
use PhpSoftBox\MultiTenant\Cli\AbstractTenantAuthRoleHandler;
use PhpSoftBox\MultiTenant\Cli\TenantAuthRoleAssignHandler;
use PhpSoftBox\MultiTenant\Cli\TenantAuthRoleDischargeHandler;
use PhpSoftBox\MultiTenant\Cli\TenantAuthRoleSyncHandler;
use PhpSoftBox\MultiTenant\Context\InMemoryTenantContextStore;
use PhpSoftBox\MultiTenant\Context\TenantContextFactory;
use PhpSoftBox\MultiTenant\Tenant\Runtime\TenantRuntimeExecutor;
use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;
use PhpSoftBox\MultiTenant\Tenant\TenantSelector;
use PhpSoftBox\MultiTenant\Tests\Support\CliTestRunner;
use PhpSoftBox\MultiTenant\Tests\Support\RecordingScopedAuthRoleOperations;
use PhpSoftBox\MultiTenant\Tests\Support\RecordingScopedAuthRoleOperationsResolver;
use PhpSoftBox\MultiTenant\Tests\Support\TestTenantProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AbstractTenantAuthRoleHandler::class)]
#[CoversClass(TenantAuthRoleAssignHandler::class)]
#[CoversClass(TenantAuthRoleDischargeHandler::class)]
#[CoversClass(TenantAuthRoleSyncHandler::class)]
final class TenantAuthRoleHandlerTest extends TestCase
{
    #[Test]
    public function syncRunsCoreAndSelectedTenantThroughInjectedOperations(): void
    {
        $core   = new RecordingScopedAuthRoleOperations();
        $tenant = new RecordingScopedAuthRoleOperations(new RoleSyncPlan(['manager'], [], [], []));

        $operations = new RecordingScopedAuthRoleOperationsResolver($core, $tenant);

        $handler = new TenantAuthRoleSyncHandler($operations, $this->selector(), $this->runtime());
        $runner  = new CliTestRunner(options: [
            'scope'   => 'all',
            'tenant'  => 'tenant-1',
            'dry-run' => true,
        ]);

        $result = $handler->run($runner);

        self::assertSame(Response::SUCCESS, $result);
        self::assertSame(['sync:dry-run'], $core->calls);
        self::assertSame(['sync:dry-run'], $tenant->calls);
        self::assertTrue($runner->containsMessage('[tenant:tenant-1] create roles: manager'));
    }

    #[Test]
    public function assignRequiresExplicitTenantForTenantScope(): void
    {
        $core   = new RecordingScopedAuthRoleOperations();
        $tenant = new RecordingScopedAuthRoleOperations();

        $operations = new RecordingScopedAuthRoleOperationsResolver($core, $tenant);

        $handler = new TenantAuthRoleAssignHandler($operations, $this->selector(), $this->runtime());
        $runner  = new CliTestRunner(params: ['user' => '42', 'role' => 'manager']);

        $result = $handler->run($runner);

        self::assertSame(Response::FAILURE, $result);
        self::assertSame([], $core->calls);
        self::assertSame([], $tenant->calls);
        self::assertTrue($runner->containsMessage('Укажите --tenant=<id> или --tenant=all'));
    }

    #[Test]
    public function assignRunsTenantOperationInsideSelectedTenantRuntime(): void
    {
        $core   = new RecordingScopedAuthRoleOperations();
        $tenant = new RecordingScopedAuthRoleOperations();

        $operations = new RecordingScopedAuthRoleOperationsResolver($core, $tenant);

        $handler = new TenantAuthRoleAssignHandler($operations, $this->selector(), $this->runtime());
        $runner  = new CliTestRunner(
            params: ['user' => '42', 'role' => 'manager'],
            options: ['tenant' => 'tenant-1'],
        );

        $result = $handler->run($runner);

        self::assertSame(Response::SUCCESS, $result);
        self::assertSame([], $core->calls);
        self::assertSame(['assign:42:manager'], $tenant->calls);
        self::assertTrue($runner->containsMessage('[tenant:tenant-1] Роль manager назначена пользователю 42.'));
    }

    #[Test]
    public function dischargeCanRunAgainstCoreScope(): void
    {
        $core   = new RecordingScopedAuthRoleOperations();
        $tenant = new RecordingScopedAuthRoleOperations();

        $operations = new RecordingScopedAuthRoleOperationsResolver($core, $tenant);

        $handler = new TenantAuthRoleDischargeHandler($operations, $this->selector(), $this->runtime());
        $runner  = new CliTestRunner(
            params: ['user' => '42', 'role' => 'manager'],
            options: ['scope' => 'core'],
        );

        $result = $handler->run($runner);

        self::assertSame(Response::SUCCESS, $result);
        self::assertSame(['discharge:42:manager'], $core->calls);
        self::assertSame([], $tenant->calls);
        self::assertTrue($runner->containsMessage('[core] Роль manager снята с пользователя 42.'));
    }

    private function selector(): TenantSelector
    {
        return new TenantSelector(new TestTenantProvider([
            new TenantDefinition('tenant-1', 'Tenant 1', null, 'tenant'),
        ]));
    }

    private function runtime(): TenantRuntimeExecutor
    {
        return new TenantRuntimeExecutor(
            new TenantContextFactory(),
            new TenantBootstrapPipeline(),
            new InMemoryTenantContextStore(),
        );
    }
}
