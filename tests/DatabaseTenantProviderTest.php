<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests;

use PhpSoftBox\Database\Connection\ConnectionManagerInterface;
use PhpSoftBox\MultiTenant\Entity\Tenant\Domain;
use PhpSoftBox\MultiTenant\Entity\Tenant\Tenant;
use PhpSoftBox\MultiTenant\Tenant\Provider\DatabaseTenantProvider;
use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;
use PhpSoftBox\Orm\Collection\EntityCollection;
use PhpSoftBox\Orm\Contracts\ConnectionEntityManagerFactoryInterface;
use PhpSoftBox\Orm\Contracts\EntityManagerInterface;
use PhpSoftBox\Orm\Contracts\EntityRepositoryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(DatabaseTenantProvider::class)]
#[CoversMethod(DatabaseTenantProvider::class, 'all')]
#[CoversMethod(DatabaseTenantProvider::class, 'findById')]
#[CoversMethod(DatabaseTenantProvider::class, 'findByHost')]
final class DatabaseTenantProviderTest extends TestCase
{
    /**
     * Проверяет загрузку tenant из ORM-репозиториев и маппинг tenant data.
     */
    #[Test]
    public function testLoadTenantsFromDatabase(): void
    {
        $provider = $this->provider(
            tenants: [
                new Tenant(
                    id: 1,
                    userId: null,
                    name: 'Tenant 1',
                    databaseConnection: 'tenant_1',
                    databaseName: 'tenant_db_1',
                    enabled: true,
                    data: [
                        'pushr'         => ['app_id' => 'pushr-1', 'secret' => 'secret-1'],
                        'timezone'      => 'UTC',
                        'telegram_bots' => [
                            [
                                'code'       => 'account',
                                'token'      => 'token-account',
                                'username'   => 'account_bot',
                                'is_default' => true,
                                'enabled'    => true,
                                'data'       => ['role' => 'main'],
                            ],
                            [
                                'code'       => 'news',
                                'token'      => '',
                                'username'   => 'news_bot',
                                'is_default' => false,
                                'enabled'    => true,
                            ],
                        ],
                    ],
                ),
                new Tenant(
                    id: 2,
                    userId: null,
                    name: 'Tenant 2',
                    databaseConnection: 'tenant_2',
                    databaseName: null,
                    enabled: false,
                    data: [],
                ),
            ],
            domains: [
                new Domain(id: 1, tenantId: 1, domain: 'tenant1.chegdesklad.local', isPrimary: true, enabled: true, data: []),
                new Domain(id: 2, tenantId: 1, domain: 'www.tenant1.chegdesklad.local', isPrimary: false, enabled: true, data: []),
                new Domain(id: 3, tenantId: 2, domain: 'tenant2.chegdesklad.local', isPrimary: true, enabled: true, data: []),
            ],
        );

        $enabled = $provider->all();
        $all     = $provider->all(false);

        $this->assertCount(1, $enabled);
        $this->assertCount(2, $all);
        $this->assertSame('1', $enabled[0]->id);
        $this->assertSame('tenant_db_1', $enabled[0]->databaseName);
        $this->assertSame('tenant1.chegdesklad.local', $enabled[0]->primaryDomain);
        $this->assertSame('pushr-1', $enabled[0]->pushrAppId);
        $this->assertSame('secret-1', $enabled[0]->pushrSecret);
        $this->assertSame(['tenant1.chegdesklad.local', 'www.tenant1.chegdesklad.local'], $enabled[0]->domains);
        $this->assertSame('UTC', $enabled[0]->data['timezone'] ?? null);
        $this->assertIsArray($enabled[0]->data['telegram_bots'] ?? null);
    }

    /**
     * Проверяет поиск tenant по id и доменам (включая зеркала).
     */
    #[Test]
    public function testFindByIdAndHost(): void
    {
        $provider = $this->provider(
            tenants: [
                new Tenant(
                    id: 1,
                    userId: null,
                    name: 'Tenant 1',
                    databaseConnection: 'tenant_1',
                    databaseName: null,
                    enabled: true,
                    data: null,
                ),
            ],
            domains: [
                new Domain(
                    id: 1,
                    tenantId: 1,
                    domain: 'tenant1.chegdesklad.local',
                    isPrimary: true,
                    enabled: true,
                    data: null,
                ),
                new Domain(
                    id: 2,
                    tenantId: 1,
                    domain: 'www.tenant1.chegdesklad.local',
                    isPrimary: false,
                    enabled: true,
                    data: null,
                ),
            ],
        );

        $byId          = $provider->findById('1');
        $byPrimaryHost = $provider->findByHost('tenant1.chegdesklad.local');
        $byMirrorHost  = $provider->findByHost('WWW.TENANT1.CHEGDESKLAD.LOCAL');

        $this->assertInstanceOf(TenantDefinition::class, $byId);
        $this->assertSame('1', $byId->id);
        $this->assertInstanceOf(TenantDefinition::class, $byPrimaryHost);
        $this->assertSame('1', $byPrimaryHost->id);
        $this->assertInstanceOf(TenantDefinition::class, $byMirrorHost);
        $this->assertSame('1', $byMirrorHost->id);
        $this->assertNull($provider->findById(''));
        $this->assertNull($provider->findByHost(''));
        $this->assertNull($provider->findByHost('unknown.chegdesklad.local'));
    }

    /**
     * Проверяет, что провайдер поддерживает кастомные entity-классы.
     */
    #[Test]
    public function testSupportsCustomEntityClasses(): void
    {
        $provider = $this->provider(
            tenants: [
                new CustomTenant(
                    id: 1,
                    userId: null,
                    name: 'Custom 1',
                    databaseConnection: 'custom_1',
                    databaseName: null,
                    enabled: true,
                    data: null,
                ),
            ],
            domains: [
                new CustomDomain(
                    id: 1,
                    tenantId: 1,
                    domain: 'custom.chegdesklad.local',
                    isPrimary: true,
                    enabled: true,
                    data: null,
                ),
            ],
            tenantClass: CustomTenant::class,
            domainClass: CustomDomain::class,
        );

        $items = $provider->all();

        $this->assertCount(1, $items);
        $this->assertSame('1', $items[0]->id);
        $this->assertSame('custom.chegdesklad.local', $items[0]->primaryDomain);
    }

    /**
     * @param list<Tenant> $tenants
     * @param list<Domain> $domains
     * @param class-string<Tenant> $tenantClass
     * @param class-string<Domain> $domainClass
     */
    private function provider(
        array $tenants,
        array $domains,
        string $tenantClass = Tenant::class,
        string $domainClass = Domain::class,
    ): DatabaseTenantProvider {
        $tenantRepository = $this->createMock(EntityRepositoryInterface::class);
        $tenantRepository->method('all')->willReturn(EntityCollection::from($tenants));

        $domainRepository = $this->createMock(EntityRepositoryInterface::class);
        $domainRepository->method('all')->willReturn(EntityCollection::from($domains));

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('repository')->willReturnCallback(
            static function (string $entityClass) use ($tenantClass, $domainClass, $tenantRepository, $domainRepository) {
                return match ($entityClass) {
                    $tenantClass => $tenantRepository,
                    $domainClass => $domainRepository,
                    default      => throw new RuntimeException('Unexpected entity class: ' . $entityClass),
                };
            },
        );

        $factory = $this->createMock(ConnectionEntityManagerFactoryInterface::class);
        $factory->method('create')->willReturn($entityManager);

        $manager = $this->createMock(ConnectionManagerInterface::class);

        return new DatabaseTenantProvider(
            connections: $manager,
            connectionName: 'default',
            tenantEntityClass: $tenantClass,
            domainEntityClass: $domainClass,
            entityManagerFactory: $factory,
        );
    }
}

class CustomTenant extends Tenant
{
}

class CustomDomain extends Domain
{
}
