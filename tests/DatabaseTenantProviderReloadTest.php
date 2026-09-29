<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests;

use PhpSoftBox\Database\Configurator\DatabaseFactory;
use PhpSoftBox\Database\Connection\ConnectionManager;
use PhpSoftBox\MultiTenant\Tenant\Provider\DatabaseTenantProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DatabaseTenantProvider::class)]
#[CoversMethod(DatabaseTenantProvider::class, 'reload')]
#[CoversMethod(DatabaseTenantProvider::class, 'findByHost')]
final class DatabaseTenantProviderReloadTest extends TestCase
{
    /**
     * Проверим, что после reload() провайдер читает актуальные строки из БД, а не сущности из identity map
     * прошлой загрузки: домен, переданный другому арендатору, должен вести к новому арендатору, а отключённый
     * арендатор — считаться отключённым (долгоживущий HTTP-воркер вызывает reload() на каждый запрос).
     *
     * @see DatabaseTenantProvider::reload()
     * @see DatabaseTenantProvider::findByHost()
     */
    #[Test]
    public function reloadReadsChangedRowsInsteadOfManagedEntities(): void
    {
        $connections = new ConnectionManager(new DatabaseFactory([
            'connections' => [
                'default' => 'core',
                'core'    => ['dsn' => 'sqlite:///:memory:'],
            ],
        ]));

        $connection = $connections->write('default');

        $connection->execute(
            '
                CREATE TABLE tenants (
                    id INTEGER PRIMARY KEY,
                    user_id INTEGER NULL,
                    name TEXT NOT NULL,
                    database_connection TEXT NOT NULL,
                    database_name TEXT NULL,
                    is_enabled INTEGER NOT NULL,
                    data TEXT NULL
                )
            ',
        );
        $connection->execute(
            '
                CREATE TABLE domains (
                    id INTEGER PRIMARY KEY,
                    tenant_id INTEGER NOT NULL,
                    domain TEXT NOT NULL,
                    is_primary INTEGER NOT NULL,
                    is_enabled INTEGER NOT NULL,
                    data TEXT NULL
                )
            ',
        );
        $connection->execute(
            '
                INSERT INTO tenants (id, name, database_connection, is_enabled)
                VALUES
                    (1, :first, :first_connection, 1),
                    (2, :second, :second_connection, 1)
            ',
            ['first' => 'A', 'first_connection' => 'tenant', 'second' => 'B', 'second_connection' => 'tenant'],
        );
        $connection->execute(
            '
                INSERT INTO domains (id, tenant_id, domain, is_primary, is_enabled)
                VALUES (1, 1, :domain, 1, 1)
            ',
            ['domain' => 'shop.test'],
        );

        $provider = new DatabaseTenantProvider($connections);

        // Первая загрузка: домен принадлежит арендатору 1.
        $this->assertSame('1', $provider->findByHost('shop.test')?->id);

        // Домен передан арендатору 2, арендатор 1 отключён.
        $connection->execute('UPDATE domains SET tenant_id = 2 WHERE id = 1');
        $connection->execute('UPDATE tenants SET is_enabled = 0 WHERE id = 1');

        $provider->reload();

        $this->assertSame('2', $provider->findByHost('shop.test')?->id);
        $this->assertFalse($provider->findById('1')?->enabled ?? true);
    }
}
