<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests;

use PhpSoftBox\MultiTenant\Contracts\TenantProviderInterface;
use PhpSoftBox\MultiTenant\Tenant\Provider\ConsistentTenantProvider;
use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function array_filter;
use function array_values;

#[CoversClass(ConsistentTenantProvider::class)]
#[CoversMethod(ConsistentTenantProvider::class, 'all')]
#[CoversMethod(ConsistentTenantProvider::class, 'findById')]
#[CoversMethod(ConsistentTenantProvider::class, 'findByHost')]
final class ConsistentTenantProviderTest extends TestCase
{
    /**
     * Проверяет успешную работу, когда primary/secondary конфиги совпадают.
     */
    #[Test]
    public function testReturnsPrimaryWhenParityIsValid(): void
    {
        $tenant = new TenantDefinition(
            id: 'tenant-1',
            name: 'Tenant 1',
            host: 'tenant1.chegdesklad.local',
            databaseConnection: 'tenant_1',
            pushrAppId: 'pushr-1',
            pushrSecret: 'secret-1',
            enabled: true,
            databaseName: 'tenant_db_1',
            primaryDomain: 'tenant1.chegdesklad.local',
            domains: ['tenant1.chegdesklad.local', 'www.tenant1.chegdesklad.local'],
        );

        $provider = new ConsistentTenantProvider(
            primary: $this->provider([$tenant]),
            secondary: $this->provider([$tenant]),
        );

        $all = $provider->all();

        $this->assertCount(1, $all);
        $this->assertSame('tenant-1', $all[0]->id);
        $this->assertSame('tenant-1', $provider->findById('tenant-1')?->id);
    }

    /**
     * Проверяет, что при расхождении источников выбрасывается исключение.
     */
    #[Test]
    public function testThrowsWhenParityMismatch(): void
    {
        $primaryTenant = new TenantDefinition(
            id: 'tenant-1',
            name: 'Tenant 1',
            host: 'tenant1.chegdesklad.local',
            databaseConnection: 'tenant_1',
            pushrAppId: 'pushr-1',
            pushrSecret: 'secret-1',
            enabled: true,
        );
        $secondaryTenant = new TenantDefinition(
            id: 'tenant-1',
            name: 'Tenant 1',
            host: 'tenant1.chegdesklad.local',
            databaseConnection: 'tenant_1',
            pushrAppId: 'pushr-2',
            pushrSecret: 'secret-2',
            enabled: true,
        );

        $provider = new ConsistentTenantProvider(
            primary: $this->provider([$primaryTenant]),
            secondary: $this->provider([$secondaryTenant]),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('не совпадают');

        $provider->all();
    }

    /**
     * @param list<TenantDefinition> $tenants
     */
    private function provider(array $tenants): TenantProviderInterface
    {
        return new class ($tenants) implements TenantProviderInterface {
            /**
             * @param list<TenantDefinition> $tenants
             */
            public function __construct(
                private array $tenants,
            ) {
            }

            public function all(bool $onlyEnabled = true): array
            {
                if (!$onlyEnabled) {
                    return $this->tenants;
                }

                return array_values(array_filter(
                    $this->tenants,
                    static fn (TenantDefinition $tenant): bool => $tenant->enabled,
                ));
            }

            public function findById(string $id): ?TenantDefinition
            {
                foreach ($this->tenants as $tenant) {
                    if ($tenant->id === $id) {
                        return $tenant;
                    }
                }

                return null;
            }

            public function findByHost(string $host): ?TenantDefinition
            {
                foreach ($this->tenants as $tenant) {
                    if ($tenant->host === $host) {
                        return $tenant;
                    }
                }

                return null;
            }
        };
    }
}
