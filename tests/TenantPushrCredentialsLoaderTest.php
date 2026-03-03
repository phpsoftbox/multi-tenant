<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests;

use PhpSoftBox\MultiTenant\Context\Loader\TenantPushrCredentialsLoader;
use PhpSoftBox\MultiTenant\Context\TenantContext;
use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TenantPushrCredentialsLoader::class)]
final class TenantPushrCredentialsLoaderTest extends TestCase
{
    #[Test]
    public function testLoadsCredentialsFromTenantDefinition(): void
    {
        $loader = new TenantPushrCredentialsLoader();

        $tenant = new TenantDefinition(
            id: 'tenant-1',
            name: 'Tenant 1',
            host: null,
            databaseConnection: 'tenant',
            pushrAppId: 'app-tenant-1',
            pushrSecret: 'secret-tenant-1',
        );

        $credentials = $loader->load($tenant, new TenantContext($tenant));

        $this->assertSame(
            ['app_id' => 'app-tenant-1', 'secret' => 'secret-tenant-1'],
            $credentials,
        );
    }

    #[Test]
    public function testLoadsCredentialsFromTenantDataAsFallback(): void
    {
        $loader = new TenantPushrCredentialsLoader();

        $tenant = new TenantDefinition(
            id: 'tenant-1',
            name: 'Tenant 1',
            host: null,
            databaseConnection: 'tenant',
            data: [
                'pushr' => [
                    'app_id' => 'app-data',
                    'secret' => 'secret-data',
                ],
            ],
        );

        $credentials = $loader->load($tenant, new TenantContext($tenant));

        $this->assertSame(
            ['app_id' => 'app-data', 'secret' => 'secret-data'],
            $credentials,
        );
    }
}
