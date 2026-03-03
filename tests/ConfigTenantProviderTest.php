<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests;

use PhpSoftBox\Config\Config;
use PhpSoftBox\MultiTenant\Tenant\Provider\ConfigTenantProvider;
use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConfigTenantProvider::class)]
#[CoversMethod(ConfigTenantProvider::class, 'all')]
#[CoversMethod(ConfigTenantProvider::class, 'findById')]
#[CoversMethod(ConfigTenantProvider::class, 'findByHost')]
final class ConfigTenantProviderTest extends TestCase
{
    /**
     * Проверяет загрузку tenant из config и фильтрацию по enabled.
     */
    #[Test]
    public function testLoadAndFilterEnabledTenants(): void
    {
        $provider = new ConfigTenantProvider(new Config([[
            'tenancy' => [
                'tenants' => [
                    'tenant-1' => [
                        'name'                => 'Tenant 1',
                        'database_connection' => 'tenant_1',
                        'database_name'       => 'tenant_db_1',
                        'domains'             => [
                            ['domain' => 'tenant1.chegdesklad.local', 'is_primary' => true],
                            ['domain' => 'www.tenant1.chegdesklad.local', 'is_primary' => false],
                        ],
                        'enabled' => true,
                        'data'    => [
                            'pushr' => [
                                'app_id' => 'app-tenant-1',
                                'secret' => 'secret-tenant-1',
                            ],
                            'timezone'      => 'UTC',
                            'telegram_bots' => [
                                [
                                    'code'       => 'account',
                                    'token'      => 'token-account',
                                    'username'   => 'account_bot',
                                    'is_default' => true,
                                ],
                            ],
                        ],
                    ],
                    'tenant-2' => [
                        'id'         => 'tenant-2',
                        'name'       => ' ',
                        'host'       => ' ',
                        'connection' => 'tenant_2',
                        'enabled'    => false,
                    ],
                    'broken' => 'not-array',
                ],
            ],
        ]]));

        $enabled = $provider->all();
        $all     = $provider->all(false);

        $this->assertCount(1, $enabled);
        $this->assertCount(2, $all);
        $this->assertSame('tenant-1', $enabled[0]->id);
        $this->assertSame('tenant_db_1', $enabled[0]->databaseName);
        $this->assertSame('tenant1.chegdesklad.local', $enabled[0]->primaryDomain);
        $this->assertSame(['tenant1.chegdesklad.local', 'www.tenant1.chegdesklad.local'], $enabled[0]->domains);
        $this->assertSame('app-tenant-1', $enabled[0]->pushrAppId);
        $this->assertSame('secret-tenant-1', $enabled[0]->pushrSecret);
        $this->assertSame('UTC', $enabled[0]->data['timezone'] ?? null);
        $this->assertIsArray($enabled[0]->data['telegram_bots'] ?? null);
        $this->assertSame('tenant-2', $all[1]->id);
        $this->assertSame('tenant-2', $all[1]->name);
        $this->assertNull($all[1]->host);
        $this->assertSame('tenant_2', $all[1]->databaseConnection);
    }

    /**
     * Проверяет поиск tenant по id и host (host без учета регистра).
     */
    #[Test]
    public function testFindTenantByIdAndHost(): void
    {
        $provider = new ConfigTenantProvider(new Config([[
            'tenancy' => [
                'tenants' => [
                    'tenant-1' => [
                        'database_connection' => 'tenant_1',
                        'domains'             => [
                            ['domain' => 'tenant1.chegdesklad.local', 'is_primary' => true],
                            ['domain' => 'www.tenant1.chegdesklad.local'],
                        ],
                    ],
                ],
            ],
        ]]));

        $byId         = $provider->findById(' tenant-1 ');
        $byHost       = $provider->findByHost('TENANT1.CHEGDESKLAD.LOCAL');
        $byMirrorHost = $provider->findByHost('www.tenant1.chegdesklad.local');

        $this->assertInstanceOf(TenantDefinition::class, $byId);
        $this->assertSame('tenant-1', $byId->id);
        $this->assertInstanceOf(TenantDefinition::class, $byHost);
        $this->assertSame('tenant-1', $byHost->id);
        $this->assertInstanceOf(TenantDefinition::class, $byMirrorHost);
        $this->assertSame('tenant-1', $byMirrorHost->id);
        $this->assertNull($provider->findById(''));
        $this->assertNull($provider->findByHost(''));
        $this->assertNull($provider->findById('unknown'));
    }
}
