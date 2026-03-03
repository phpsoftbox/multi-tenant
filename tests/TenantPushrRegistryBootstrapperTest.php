<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests;

use PhpSoftBox\MultiTenant\Bootstrap\TenantPushrRegistryBootstrapper;
use PhpSoftBox\MultiTenant\Context\TenantContext;
use PhpSoftBox\MultiTenant\Contracts\PushrRegistrySwitcherInterface;
use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TenantPushrRegistryBootstrapper::class)]
final class TenantPushrRegistryBootstrapperTest extends TestCase
{
    #[Test]
    public function testBootstrapAndTeardownUseSwitcher(): void
    {
        $switcher     = $this->createMock(PushrRegistrySwitcherInterface::class);
        $bootstrapper = new TenantPushrRegistryBootstrapper($switcher);

        $tenant = new TenantDefinition('tenant-1', 'Tenant 1', null, 'tenant');

        $context = new TenantContext($tenant)->with(
            'pushr.credentials',
            ['app_id' => 'app-tenant-1', 'secret' => 'secret-tenant-1'],
        );

        $switcher->expects($this->once())
            ->method('activate')
            ->with(['app_id' => 'app-tenant-1', 'secret' => 'secret-tenant-1'], $context);

        $switcher->expects($this->once())
            ->method('deactivate')
            ->with($context);

        $bootstrapper->bootstrap($context);
        $bootstrapper->teardown($context);
    }
}
