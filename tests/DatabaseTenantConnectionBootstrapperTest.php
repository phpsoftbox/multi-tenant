<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests;

use PhpSoftBox\Config\Config;
use PhpSoftBox\MultiTenant\Bootstrap\DatabaseTenantConnectionBootstrapper;
use PhpSoftBox\MultiTenant\Context\TenantContext;
use PhpSoftBox\MultiTenant\Contracts\TenantConnectionSwitcherInterface;
use PhpSoftBox\MultiTenant\Database\TenantDsnResolver;
use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DatabaseTenantConnectionBootstrapper::class)]
final class DatabaseTenantConnectionBootstrapperTest extends TestCase
{
    #[Test]
    public function testBootstrapActivatesAndTeardownDeactivatesSwitcher(): void
    {
        $switcher = $this->createMock(TenantConnectionSwitcherInterface::class);
        $resolver = new TenantDsnResolver(new Config([[
            'database' => [
                'connections' => [
                    'tenant' => [
                        'dsn' => 'mariadb://app:app@mariadb:3306/tenant',
                    ],
                ],
            ],
        ]]));

        $bootstrapper = new DatabaseTenantConnectionBootstrapper($switcher, $resolver);
        $context      = new TenantContext(new TenantDefinition(
            id: 'tenant-1',
            name: 'Tenant 1',
            host: null,
            databaseConnection: 'tenant',
            databaseName: 'tenant_1',
        ));

        $switcher->expects($this->once())
            ->method('activate')
            ->with('mariadb://app:app@mariadb:3306/tenant_1');

        $switcher->expects($this->once())
            ->method('deactivate');

        $bootstrapper->bootstrap($context);
        $bootstrapper->teardown($context);
    }
}
