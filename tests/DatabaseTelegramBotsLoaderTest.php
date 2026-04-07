<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests;

use PhpSoftBox\Database\Connection\ConnectionManagerInterface;
use PhpSoftBox\MultiTenant\Context\Loader\DatabaseTelegramBotsLoader;
use PhpSoftBox\MultiTenant\Context\TenantContext;
use PhpSoftBox\MultiTenant\Entity\Tenant\TelegramBot;
use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;
use PhpSoftBox\Orm\Collection\EntityCollection;
use PhpSoftBox\Orm\Contracts\ConnectionEntityManagerFactoryInterface;
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
                tenantId: 1,
                code: 'main',
                token: 'token-main',
                username: null,
                isDefault: false,
                enabled: true,
                data: null,
            ),
            new TelegramBot(
                id: 2,
                tenantId: 1,
                code: 'account',
                token: 'token-account',
                username: null,
                isDefault: true,
                enabled: true,
                data: null,
            ),
            new TelegramBot(
                id: 3,
                tenantId: 2,
                code: 'other',
                token: 'token-other',
                username: null,
                isDefault: true,
                enabled: true,
                data: null,
            ),
        ]);

        $tenant = new TenantDefinition('1', 'Tenant 1', null, 'tenant');

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
            id: '1',
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

    #[Test]
    public function testFallsBackToTenantTelegramNodeWhenDatabaseHasNoRows(): void
    {
        $loader = $this->loader([]);

        $tenant = new TenantDefinition(
            id: '1',
            name: 'Tenant 1',
            host: null,
            databaseConnection: 'tenant',
            data: [
                'telegram' => [
                    'bot_name'        => '@MainBot',
                    'bot_code'        => 'main',
                    'bot_token'       => 'token-main',
                    'welcome_message' => 'Welcome',
                ],
            ],
        );

        $config = $loader->load($tenant, new TenantContext($tenant));

        $this->assertIsArray($config);
        $this->assertSame('main', $config['default'] ?? null);
        $bots = $config['bots'] ?? [];
        $this->assertCount(1, $bots);
        $this->assertSame('main', $bots[0]['code'] ?? null);
        $this->assertSame('token-main', $bots[0]['token'] ?? null);
        $this->assertSame('MainBot', $bots[0]['username'] ?? null);
        $this->assertSame('Welcome', $bots[0]['data']['welcome_message'] ?? null);
    }

    #[Test]
    public function testReturnsEmptyConfigWhenBotCodeAndBotNameAreMissingInTelegramNode(): void
    {
        $loader = $this->loader([]);

        $tenant = new TenantDefinition(
            id: '1',
            name: 'Tenant 1',
            host: null,
            databaseConnection: 'tenant',
            data: [
                'telegram' => [
                    'bot_token' => 'token-main-tenant',
                ],
            ],
        );

        $config = $loader->load($tenant, new TenantContext($tenant));

        $this->assertIsArray($config);
        $this->assertSame([], $config['bots'] ?? []);
        $this->assertNull($config['default'] ?? null);
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

        $factory = $this->createMock(ConnectionEntityManagerFactoryInterface::class);
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
