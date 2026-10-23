<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests;

use PDO;
use PhpSoftBox\Config\Config;
use PhpSoftBox\Database\Configurator\DatabaseFactory;
use PhpSoftBox\Database\Connection\ConnectionManager;
use PhpSoftBox\Database\Connection\WarmupAwareConnectionManagerInterface;
use PhpSoftBox\Database\Contracts\WarmupAwareConnectionInterface;
use PhpSoftBox\DatabaseLookup\LookupSpec;
use PhpSoftBox\MultiTenant\Database\TenantAwareConnectionManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function random_bytes;
use function sys_get_temp_dir;
use function unlink;

#[CoversClass(TenantAwareConnectionManager::class)]
#[CoversMethod(TenantAwareConnectionManager::class, 'clearWarmup')]
final class TenantAwareConnectionManagerWarmupTest extends TestCase
{
    /**
     * Проверим, что сброс очищает прогретые строки базового и активного tenant-подключений.
     *
     * @see TenantAwareConnectionManager::clearWarmup()
     * @see ConnectionManager::clearWarmup()
     */
    #[Test]
    public function clearsBaseAndTenantWarmupStores(): void
    {
        $file = sys_get_temp_dir() . '/psb_tenant_warmup_' . bin2hex(random_bytes(6)) . '.sqlite';

        try {
            $pdo = new PDO('sqlite:' . $file);

            $pdo->exec('CREATE TABLE products (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
            $pdo->exec('INSERT INTO products (id, name) VALUES (1, \'A\')');

            $dsn    = 'sqlite:///' . $file;
            $config = [
                'connections' => [
                    'default' => 'main',
                    'main'    => ['dsn' => $dsn],
                    'tenant'  => ['dsn' => $dsn],
                ],
            ];

            $baseManager = new ConnectionManager(new DatabaseFactory($config));

            $manager = new TenantAwareConnectionManager(
                baseManager: $baseManager,
                config: new Config([['database' => $config]]),
            );

            self::assertInstanceOf(WarmupAwareConnectionManagerInterface::class, $baseManager);
            self::assertInstanceOf(WarmupAwareConnectionManagerInterface::class, $manager);

            $manager->activate($dsn);

            $base   = $manager->connection('main');
            $tenant = $manager->connection('tenant');
            self::assertInstanceOf(WarmupAwareConnectionInterface::class, $base);
            self::assertInstanceOf(WarmupAwareConnectionInterface::class, $tenant);

            $lookup = LookupSpec::forTable('products')->lookupColumn('id')->value(1);
            self::assertSame('A', $base->warmup()->one($lookup)['name'] ?? null);
            self::assertSame('A', $tenant->warmup()->one($lookup)['name'] ?? null);

            $pdo->exec('UPDATE products SET name = \'B\' WHERE id = 1');
            self::assertSame('A', $base->warmup()->one($lookup)['name'] ?? null);
            self::assertSame('A', $tenant->warmup()->one($lookup)['name'] ?? null);

            $manager->clearWarmup();

            self::assertSame('B', $base->warmup()->one($lookup)['name'] ?? null);
            self::assertSame('B', $tenant->warmup()->one($lookup)['name'] ?? null);
        } finally {
            unlink($file);
        }
    }
}
