<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests;

use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\Database;
use PhpSoftBox\Config\Config;
use PhpSoftBox\MongoDb\Connection\MongoConnectionManagerInterface;
use PhpSoftBox\MultiTenant\Context\InMemoryTenantContextStore;
use PhpSoftBox\MultiTenant\Context\TenantContext;
use PhpSoftBox\MultiTenant\Context\TenantContextResolver;
use PhpSoftBox\MultiTenant\Mongo\TenantMongoDatabaseProvider;
use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function class_exists;

#[CoversClass(TenantMongoDatabaseProvider::class)]
final class TenantMongoDatabaseProviderTest extends TestCase
{
    #[Test]
    public function databaseUsesTenantMongoDataWhenDefined(): void
    {
        $this->requireMongoExtension();

        $collection = $this->createMock(Collection::class);
        $database   = $this->createMock(Database::class);
        $client     = $this->createMock(Client::class);
        $mongo      = $this->createMock(MongoConnectionManagerInterface::class);

        $database->expects($this->once())
            ->method('selectCollection')
            ->with('entity_changelog')
            ->willReturn($collection);
        $client->expects($this->once())
            ->method('selectDatabase')
            ->with('tenant_custom_db')
            ->willReturn($database);
        $mongo->expects($this->once())
            ->method('client')
            ->with('tenant_custom_connection')
            ->willReturn($client);

        $store = new InMemoryTenantContextStore();

        $store->set(new TenantContext(new TenantDefinition(
            id: 'tenant-id',
            name: 'Tenant',
            host: null,
            databaseConnection: 'tenant',
            databaseName: 'tenant_default_db',
            data: [
                'mongo' => [
                    'connection' => 'tenant_custom_connection',
                    'database'   => 'tenant_custom_db',
                ],
            ],
        )));

        $provider = new TenantMongoDatabaseProvider(
            mongo: $mongo,
            tenantResolver: new TenantContextResolver($store),
            config: new Config([[
                'mongo' => [
                    'tenant' => [
                        'template_connection' => 'template_connection',
                    ],
                    'connections' => [
                        'default' => 'default_connection',
                    ],
                ],
            ]]),
        );

        self::assertSame($collection, $provider->collection('entity_changelog'));
    }

    #[Test]
    public function databaseFallsBackToTemplateConnectionAndTenantDatabaseName(): void
    {
        $this->requireMongoExtension();

        $database = $this->createMock(Database::class);
        $client   = $this->createMock(Client::class);
        $mongo    = $this->createMock(MongoConnectionManagerInterface::class);

        $client->expects($this->once())
            ->method('selectDatabase')
            ->with('tenant_database')
            ->willReturn($database);
        $mongo->expects($this->once())
            ->method('client')
            ->with('tenant_template')
            ->willReturn($client);

        $store = new InMemoryTenantContextStore();

        $store->set(new TenantContext(new TenantDefinition(
            id: 'tenant-id',
            name: 'Tenant',
            host: null,
            databaseConnection: 'tenant',
            databaseName: 'tenant_database',
        )));

        $provider = new TenantMongoDatabaseProvider(
            mongo: $mongo,
            tenantResolver: new TenantContextResolver($store),
            config: new Config([[
                'mongo' => [
                    'tenant' => [
                        'template_connection' => 'tenant_template',
                    ],
                    'connections' => [
                        'default' => 'default_connection',
                    ],
                ],
            ]]),
        );

        self::assertSame($database, $provider->database());
    }

    #[Test]
    public function collectionThrowsWhenNameIsEmpty(): void
    {
        $this->requireMongoExtension();

        $mongo = $this->createMock(MongoConnectionManagerInterface::class);
        $store = new InMemoryTenantContextStore();

        $store->set(new TenantContext(new TenantDefinition(
            id: 'tenant-id',
            name: 'Tenant',
            host: null,
            databaseConnection: 'tenant',
            databaseName: 'tenant_database',
        )));

        $provider = new TenantMongoDatabaseProvider(
            mongo: $mongo,
            tenantResolver: new TenantContextResolver($store),
            config: new Config([[]]),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Mongo collection name must be non-empty string.');

        $provider->collection('   ');
    }

    private function requireMongoExtension(): void
    {
        if (!class_exists(Client::class)) {
            $this->markTestSkipped('ext-mongodb is required for TenantMongoDatabaseProvider tests.');
        }
    }
}
