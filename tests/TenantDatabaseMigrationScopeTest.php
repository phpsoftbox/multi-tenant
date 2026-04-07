<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests;

use PhpSoftBox\Config\Config;
use PhpSoftBox\MultiTenant\Contracts\TenantConnectionSwitcherInterface;
use PhpSoftBox\MultiTenant\Database\TenantDatabaseMigrationScope;
use PhpSoftBox\MultiTenant\Database\TenantDsnResolver;
use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(TenantDatabaseMigrationScope::class)]
final class TenantDatabaseMigrationScopeTest extends TestCase
{
    #[Test]
    public function testRunActivatesTenantDsnAndDeactivatesAfterCallback(): void
    {
        $switcher = new SpyTenantConnectionSwitcher();

        $scope  = new TenantDatabaseMigrationScope($this->resolver(), $switcher);
        $tenant = new TenantDefinition(
            id: 'tenant-1',
            name: 'Tenant 1',
            host: null,
            databaseConnection: 'tenant',
            databaseName: 'tenant_1',
        );

        $result = $scope->run($tenant, static function (string $connectionName) use ($switcher): array {
            self::assertSame('tenant', $connectionName);
            self::assertSame('mariadb://app:app@mariadb:3306/tenant_1', $switcher->activeDsn());

            return ['20260101000000_demo'];
        });

        $this->assertSame(['20260101000000_demo'], $result);
        $this->assertSame(['activate:mariadb://app:app@mariadb:3306/tenant_1', 'deactivate'], $switcher->calls);
        $this->assertNull($switcher->activeDsn());
    }

    #[Test]
    public function testRunDeactivatesTenantDsnWhenCallbackThrows(): void
    {
        $switcher = new SpyTenantConnectionSwitcher();

        $scope  = new TenantDatabaseMigrationScope($this->resolver(), $switcher);
        $tenant = new TenantDefinition(
            id: 'tenant-1',
            name: 'Tenant 1',
            host: null,
            databaseConnection: 'tenant',
            databaseName: 'tenant_1',
        );

        try {
            $scope->run($tenant, static function () use ($switcher): never {
                self::assertSame('mariadb://app:app@mariadb:3306/tenant_1', $switcher->activeDsn());

                throw new RuntimeException('Migration failed.');
            });

            self::fail('Expected migration exception.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Migration failed.', $exception->getMessage());
        }

        $this->assertSame(['activate:mariadb://app:app@mariadb:3306/tenant_1', 'deactivate'], $switcher->calls);
        $this->assertNull($switcher->activeDsn());
    }

    private function resolver(): TenantDsnResolver
    {
        return new TenantDsnResolver(new Config([[
            'database' => [
                'connections' => [
                    'tenant' => [
                        'dsn' => 'mariadb://app:app@mariadb:3306/template',
                    ],
                ],
            ],
        ]]));
    }
}

final class SpyTenantConnectionSwitcher implements TenantConnectionSwitcherInterface
{
    /**
     * @var list<string>
     */
    public array $calls = [];

    private ?string $activeDsn = null;

    public function activate(string $dsn): void
    {
        $this->calls[]   = 'activate:' . $dsn;
        $this->activeDsn = $dsn;
    }

    public function deactivate(): void
    {
        $this->calls[]   = 'deactivate';
        $this->activeDsn = null;
    }

    public function activeDsn(): ?string
    {
        return $this->activeDsn;
    }
}
