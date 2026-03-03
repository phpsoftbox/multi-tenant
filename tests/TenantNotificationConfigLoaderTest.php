<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests;

use PhpSoftBox\MultiTenant\Context\Loader\TenantNotificationConfigLoader;
use PhpSoftBox\MultiTenant\Context\TenantContext;
use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TenantNotificationConfigLoader::class)]
final class TenantNotificationConfigLoaderTest extends TestCase
{
    #[Test]
    public function testLoadsConfigFromNotificationsConfig(): void
    {
        $loader = new TenantNotificationConfigLoader();

        $tenant = new TenantDefinition(
            id: 'tenant-1',
            name: 'Tenant 1',
            host: null,
            databaseConnection: 'tenant',
            data: [
                'notifications_config' => [
                    'mail' => [
                        'smtp' => [
                            'from' => 'no-reply@tenant.local',
                        ],
                    ],
                ],
            ],
        );

        $config = $loader->load($tenant, new TenantContext($tenant));

        $this->assertSame([
            'mail' => [
                'smtp' => [
                    'from' => 'no-reply@tenant.local',
                ],
            ],
        ], $config);
    }

    #[Test]
    public function testLoadsConfigFromNotificationsDotConfigFallback(): void
    {
        $loader = new TenantNotificationConfigLoader();

        $tenant = new TenantDefinition(
            id: 'tenant-1',
            name: 'Tenant 1',
            host: null,
            databaseConnection: 'tenant',
            data: [
                'notifications' => [
                    'config' => [
                        'notifications' => [
                            'email' => [
                                'view_path' => 'resources/views/tenant',
                            ],
                        ],
                    ],
                ],
            ],
        );

        $config = $loader->load($tenant, new TenantContext($tenant));

        $this->assertSame([
            'notifications' => [
                'email' => [
                    'view_path' => 'resources/views/tenant',
                ],
            ],
        ], $config);
    }
}
