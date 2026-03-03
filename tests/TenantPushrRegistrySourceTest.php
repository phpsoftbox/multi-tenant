<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests;

use PhpSoftBox\MultiTenant\Contracts\TenantProviderInterface;
use PhpSoftBox\MultiTenant\Pushr\TenantPushrRegistrySource;
use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;
use PhpSoftBox\MultiTenant\Tenant\TenantSelector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TenantPushrRegistrySource::class)]
final class TenantPushrRegistrySourceTest extends TestCase
{
    public function testAppsReadsCredentialsFromTenantDefinition(): void
    {
        $provider = $this->createMock(TenantProviderInterface::class);
        $provider
            ->expects(self::once())
            ->method('all')
            ->with(true)
            ->willReturn([
                new TenantDefinition(
                    id: '1',
                    name: 'Tenant 1',
                    host: 'tenant1.example.test',
                    databaseConnection: 'tenant',
                    pushrAppId: 'tenant-1',
                    pushrSecret: 'tenant-secret-1',
                ),
            ]);

        $selector = new TenantSelector($provider);

        $source = new TenantPushrRegistrySource($selector);

        self::assertSame([
            'tenant-1' => 'tenant-secret-1',
        ], $source->apps(['tenant' => 'all']));
    }

    public function testAppsReadsCredentialsFromTenantDataFallback(): void
    {
        $provider = $this->createMock(TenantProviderInterface::class);
        $provider
            ->expects(self::once())
            ->method('all')
            ->with(true)
            ->willReturn([
                new TenantDefinition(
                    id: '2',
                    name: 'Tenant 2',
                    host: 'tenant2.example.test',
                    databaseConnection: 'tenant',
                    pushrAppId: null,
                    pushrSecret: null,
                    data: [
                        'pushr' => [
                            'app_id' => 'tenant-2',
                            'secret' => 'tenant-secret-2',
                        ],
                    ],
                ),
            ]);

        $selector = new TenantSelector($provider);

        $source = new TenantPushrRegistrySource($selector);

        self::assertSame([
            'tenant-2' => 'tenant-secret-2',
        ], $source->apps(['tenant' => 'all']));
    }
}
