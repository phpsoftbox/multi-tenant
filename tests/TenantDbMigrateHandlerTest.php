<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests;

use PhpSoftBox\CliApp\Response;
use PhpSoftBox\Config\Config;
use PhpSoftBox\Database\Connection\ConnectionManagerInterface;
use PhpSoftBox\Database\Migrations\MigrationRepositoryInterface;
use PhpSoftBox\Database\Migrations\MigrationsConfig;
use PhpSoftBox\MultiTenant\Cli\TenantDbMigrateHandler;
use PhpSoftBox\MultiTenant\Database\TenantDatabaseMigrationScope;
use PhpSoftBox\MultiTenant\Database\TenantDatabaseMigrationService;
use PhpSoftBox\MultiTenant\Database\TenantDsnResolver;
use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;
use PhpSoftBox\MultiTenant\Tenant\TenantSelector;
use PhpSoftBox\MultiTenant\Tests\Support\CliTestRunner;
use PhpSoftBox\MultiTenant\Tests\Support\StackTenantConnectionSwitcher;
use PhpSoftBox\MultiTenant\Tests\Support\TestTenantProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;

#[CoversClass(TenantDbMigrateHandler::class)]
#[CoversMethod(TenantDbMigrateHandler::class, 'run')]
final class TenantDbMigrateHandlerTest extends TestCase
{
    private string $migrationsPath;

    protected function setUp(): void
    {
        $this->migrationsPath = sys_get_temp_dir() . '/psb-mt-migrations-' . uniqid('', true);
        mkdir($this->migrationsPath . '/tenant', 0777, true);
    }

    protected function tearDown(): void
    {
        rmdir($this->migrationsPath . '/tenant');
        rmdir($this->migrationsPath);
    }

    /**
     * Проверим, что миграции применяются к явно указанному отключённому арендатору: его БД готовят до включения.
     *
     * @see TenantDbMigrateHandler::run()
     */
    #[Test]
    public function migratesExplicitDisabledTenant(): void
    {
        $tenant = new TenantDefinition(
            id: 'disabled-tenant',
            name: 'Disabled',
            host: null,
            databaseConnection: 'tenant',
            enabled: false,
            databaseName: 'tenant_disabled',
        );

        $switcher = new StackTenantConnectionSwitcher();

        $handler = new TenantDbMigrateHandler(
            selector: new TenantSelector(new TestTenantProvider([$tenant])),
            migrations: new TenantDatabaseMigrationService(
                connections: $this->createStub(ConnectionManagerInterface::class),
                config: new MigrationsConfig($this->migrationsPath),
                repository: $this->createStub(MigrationRepositoryInterface::class),
            ),
            migrationScope: new TenantDatabaseMigrationScope(
                new TenantDsnResolver(new Config([[
                    'database' => [
                        'connections' => [
                            'tenant' => [
                                'dsn' => 'mariadb://app:app@mariadb:3306/template',
                            ],
                        ],
                    ],
                ]])),
                $switcher,
            ),
        );

        $runner = new CliTestRunner(options: ['tenant' => 'disabled-tenant']);

        $result = $handler->run($runner);

        // Миграции выполнены в БД отключённого арендатора.
        $this->assertSame(Response::SUCCESS, $result);
        $this->assertSame(['activate:mariadb://app:app@mariadb:3306/tenant_disabled', 'deactivate:none'], $switcher->calls);
        $this->assertTrue($runner->containsMessage('[tenant:disabled-tenant] migrate'));
    }
}
