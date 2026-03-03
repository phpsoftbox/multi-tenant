<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests;

use PhpSoftBox\Config\Config;
use PhpSoftBox\MultiTenant\Context\TenantContext;
use PhpSoftBox\MultiTenant\Switcher\NotificationConfigSwitcher;
use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(NotificationConfigSwitcher::class)]
final class NotificationConfigSwitcherTest extends TestCase
{
    #[Test]
    public function testActivateOverridesAndDeactivateRestoresValues(): void
    {
        $config = new Config(
            sources: [[
                'mail' => [
                    'smtp' => [
                        'from' => 'no-reply@dispatcher.local',
                    ],
                    'brand' => [
                        'name' => 'Dispatcher',
                    ],
                ],
                'notifications' => [
                    'email' => [
                        'view_path' => 'resources/views',
                    ],
                ],
            ]],
            readOnly: true,
        );

        $switcher = new NotificationConfigSwitcher($config->mutable());
        $context  = $this->context();

        $switcher->activate([
            'mail' => [
                'smtp' => [
                    'from' => 'no-reply@tenant.local',
                ],
                'brand' => [
                    'name' => 'Tenant',
                ],
            ],
            'notifications' => [
                'email' => [
                    'view_path' => 'resources/views/tenant',
                ],
            ],
        ], $context);

        $this->assertSame('no-reply@tenant.local', $config->get('mail.smtp.from'));
        $this->assertSame('Tenant', $config->get('mail.brand.name'));
        $this->assertSame('resources/views/tenant', $config->get('notifications.email.view_path'));

        $switcher->deactivate($context);

        $this->assertSame('no-reply@dispatcher.local', $config->get('mail.smtp.from'));
        $this->assertSame('Dispatcher', $config->get('mail.brand.name'));
        $this->assertSame('resources/views', $config->get('notifications.email.view_path'));
    }

    #[Test]
    public function testNestedActivationsRestoreByStackOrder(): void
    {
        $config = new Config(
            sources: [[
                'mail' => [
                    'smtp' => [
                        'from' => 'no-reply@dispatcher.local',
                    ],
                ],
            ]],
            readOnly: true,
        );

        $switcher = new NotificationConfigSwitcher($config->mutable());
        $context  = $this->context();

        $switcher->activate(['mail' => ['smtp' => ['from' => 'no-reply@tenant-1.local']]], $context);
        $switcher->activate(['mail' => ['smtp' => ['from' => 'no-reply@tenant-2.local']]], $context);

        $this->assertSame('no-reply@tenant-2.local', $config->get('mail.smtp.from'));

        $switcher->deactivate($context);
        $this->assertSame('no-reply@tenant-1.local', $config->get('mail.smtp.from'));

        $switcher->deactivate($context);
        $this->assertSame('no-reply@dispatcher.local', $config->get('mail.smtp.from'));
    }

    private function context(): TenantContext
    {
        return new TenantContext(new TenantDefinition('tenant-alpha', 'Tenant Alpha', null, 'tenant'));
    }
}
