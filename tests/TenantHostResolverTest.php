<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests;

use PhpSoftBox\Config\Config;
use PhpSoftBox\MultiTenant\Tenant\Host\CentralDomainPolicy;
use PhpSoftBox\MultiTenant\Tenant\Host\TenantHostResolver;
use PhpSoftBox\MultiTenant\Tenant\Provider\ConfigTenantProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CentralDomainPolicy::class)]
#[CoversClass(TenantHostResolver::class)]
final class TenantHostResolverTest extends TestCase
{
    #[Test]
    public function testResolvesCentralDomainAndTenantDomain(): void
    {
        $provider = new ConfigTenantProvider(new Config([[
            'tenancy' => [
                'tenants' => [
                    'tenant-1' => [
                        'database_connection' => 'tenant',
                        'domains'             => [
                            ['domain' => 'tenant1.chegdesklad.local', 'is_primary' => true],
                        ],
                    ],
                ],
            ],
        ]]));

        $resolver = new TenantHostResolver(
            provider: $provider,
            centralDomains: new CentralDomainPolicy(['chegdesklad.local', '*.admin.chegdesklad.local']),
        );

        $this->assertTrue($resolver->resolve('chegdesklad.local')->isCentral());
        $this->assertTrue($resolver->resolve('ops.admin.chegdesklad.local')->isCentral());
        $this->assertTrue($resolver->resolve('tenant1.chegdesklad.local')->isTenant());
        $this->assertTrue($resolver->resolve('unknown.chegdesklad.local')->isNotFound());
    }
}
