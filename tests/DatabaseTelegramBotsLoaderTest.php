<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests;

use PhpSoftBox\Database\Connection\ConnectionManagerInterface;
use PhpSoftBox\MultiTenant\Context\Loader\DatabaseTelegramBotsLoader;
use PhpSoftBox\MultiTenant\Context\TenantContext;
use PhpSoftBox\MultiTenant\Contracts\TenantEntityManagerFactoryInterface;
use PhpSoftBox\MultiTenant\Entity\Tenant\TelegramBot;
use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;
use PhpSoftBox\Orm\Collection\EntityCollection;
use PhpSoftBox\Orm\Contracts\EntityManagerInterface;
use PhpSoftBox\Orm\Contracts\EntityRepositoryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function array_map;

#[CoversClass(DatabaseTelegramBotsLoader::class)]
final class DatabaseTelegramBotsLoaderTest extends TestCase
{
    #[Test]
    public function testLoadsBotsFromDatabaseAndSortsDefaultFirst(): void
    {
        $loader = $this->loader([
            new TelegramBot(
                id: 1,
                tenantId: 'tenant-1',
                code: 'main',
                token: 'token-main',
                isDefault: false,
                enabled: true,
            ),
            new TelegramBot(
                id: 2,
                tenantId: 'tenant-1',
                code: 'account',
                token: 'token-account',
                isDefault: true,
                enabled: true,
            ),
            new TelegramBot(
                id: 3,
                tenantId: 'tenant-2',
                code: 'other',
                token: 'token-other',
                isDefault: true,
                enabled: true,
            ),
        ]);

        $tenant = new TenantDefinition('tenant-1', 'Tenant 1', null, 'tenant');

        $config = $loader->load($tenant, new TenantContext($tenant));

        $this->assertIsArray($config);
        $this->assertSame('account', $config['default'] ?? null);
        $this->assertSame(
            ['account', 'main'],
            array_map(
                static fn (array $bot): string => (string) ($bot['code'] ?? ''),
                $config['bots'] ?? [],
            ),
        );
    }

    #[Test]
    public function testFallsBackToTenantDataWhenDatabaseHasNoRows(): void
    {
        $loader = $this->loader([]);

        $tenant = new TenantDefinition(
            id: 'tenant-1',
            name: 'Tenant 1',
            host: null,
            databaseConnection: 'tenant',
            data: [
                'telegram_bots' => [
                    [
                        'code'       => 'account',
                        'token'      => 'token-account',
                        'is_default' => true,
                        'enabled'    => true,
                    ],
                ],
            ],
        );

        $config = $loader->load($tenant, new TenantContext($tenant));

        $this->assertIsArray($config);
        $this->assertSame('account', $config['default'] ?? null);
        $this->assertSame(
            ['account'],
            array_map(
                static fn (array $bot): string => (string) ($bot['code'] ?? ''),
                $config['bots'] ?? [],
            ),
        );
    }

    /**
     * @param list<TelegramBot> $bots
     */
    private function loader(array $bots): DatabaseTelegramBotsLoader
    {
        $repository = $this->createMock(EntityRepositoryInterface::class);
        $repository->method('all')->willReturn(EntityCollection::from($bots));

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('repository')->willReturnCallback(
            static function (string $entityClass) use ($repository) {
                return match ($entityClass) {
                    TelegramBot::class => $repository,
                    default            => throw new RuntimeException('Unexpected entity class: ' . $entityClass),
                };
            },
        );

        $factory = $this->createMock(TenantEntityManagerFactoryInterface::class);
        $factory->method('create')->willReturn($entityManager);

        $connections = $this->createMock(ConnectionManagerInterface::class);

        return new DatabaseTelegramBotsLoader(
            connections: $connections,
            connectionName: 'default',
            telegramBotEntityClass: TelegramBot::class,
            entityManagerFactory: $factory,
        );
    }
}
