<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests;

use PhpSoftBox\MultiTenant\Bootstrap\TenantTelegramRegistryBootstrapper;
use PhpSoftBox\MultiTenant\Context\TenantContext;
use PhpSoftBox\MultiTenant\Contracts\TelegramRegistrySwitcherInterface;
use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TenantTelegramRegistryBootstrapper::class)]
final class TenantTelegramRegistryBootstrapperTest extends TestCase
{
    #[Test]
    public function testBootstrapAndTeardownUseSwitcher(): void
    {
        $switcher     = $this->createMock(TelegramRegistrySwitcherInterface::class);
        $bootstrapper = new TenantTelegramRegistryBootstrapper($switcher);

        $tenant = new TenantDefinition('tenant-1', 'Tenant 1', null, 'tenant');

        $context = new TenantContext($tenant)->with(
            'telegram.bots',
            [
                'bots' => [
                    ['code' => 'account', 'token' => 'token-account', 'is_default' => true],
                ],
                'default' => 'account',
            ],
        );

        $switcher->expects($this->once())
            ->method('activate')
            ->with(
                [
                    'bots' => [
                        ['code' => 'account', 'token' => 'token-account', 'is_default' => true],
                    ],
                    'default' => 'account',
                ],
                $context,
            );

        $switcher->expects($this->once())
            ->method('deactivate')
            ->with($context);

        $bootstrapper->bootstrap($context);
        $bootstrapper->teardown($context);
    }
}
