<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests;

use PhpSoftBox\MultiTenant\Bootstrap\TenantNotificationConfigBootstrapper;
use PhpSoftBox\MultiTenant\Context\TenantContext;
use PhpSoftBox\MultiTenant\Contracts\NotificationConfigSwitcherInterface;
use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TenantNotificationConfigBootstrapper::class)]
final class TenantNotificationConfigBootstrapperTest extends TestCase
{
    #[Test]
    public function testBootstrapUsesContextExtensionWhenPresent(): void
    {
        $switcher     = $this->createMock(NotificationConfigSwitcherInterface::class);
        $bootstrapper = new TenantNotificationConfigBootstrapper($switcher);

        $context = new TenantContext(
            new TenantDefinition('tenant-1', 'Tenant 1', null, 'tenant'),
        )->with('notifications.config', [
            'mail' => [
                'smtp' => [
                    'from' => 'no-reply@tenant-1.local',
                ],
            ],
        ]);

        $switcher->expects($this->once())
            ->method('activate')
            ->with([
                'mail' => [
                    'smtp' => [
                        'from' => 'no-reply@tenant-1.local',
                    ],
                ],
            ], $context);

        $switcher->expects($this->once())
            ->method('deactivate')
            ->with($context);

        $bootstrapper->bootstrap($context);
        $bootstrapper->teardown($context);
    }

    #[Test]
    public function testBootstrapFallsBackToTenantDataNotificationsConfig(): void
    {
        $switcher     = $this->createMock(NotificationConfigSwitcherInterface::class);
        $bootstrapper = new TenantNotificationConfigBootstrapper($switcher);

        $context = new TenantContext(new TenantDefinition(
            id: 'tenant-1',
            name: 'Tenant 1',
            host: null,
            databaseConnection: 'tenant',
            data: [
                'notifications_config' => [
                    'notifications' => [
                        'email' => [
                            'view_path' => 'resources/views/tenant',
                        ],
                    ],
                ],
            ],
        ));

        $switcher->expects($this->once())
            ->method('activate')
            ->with([
                'notifications' => [
                    'email' => [
                        'view_path' => 'resources/views/tenant',
                    ],
                ],
            ], $context);

        $bootstrapper->bootstrap($context);
    }
}
