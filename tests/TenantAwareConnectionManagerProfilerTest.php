<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests;

use PhpSoftBox\Config\Config;
use PhpSoftBox\Database\Configurator\DatabaseFactory;
use PhpSoftBox\Database\Connection\ConnectionManager;
use PhpSoftBox\Database\Profiler\DatabaseProfilerCollector;
use PhpSoftBox\MultiTenant\Database\TenantAwareConnectionManager;
use PhpSoftBox\MultiTenant\Profiler\MultiTenantProfilerCollector;
use PhpSoftBox\Profiler\Profiler;
use PhpSoftBox\Profiler\ProfilerRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_column;

#[CoversClass(TenantAwareConnectionManager::class)]
#[CoversClass(MultiTenantProfilerCollector::class)]
final class TenantAwareConnectionManagerProfilerTest extends TestCase
{
    /**
     * Проверяем, что runtime tenant-подключение получает profiler-зависимости и tenant SQL
     * попадает в общий database collector.
     */
    #[Test]
    public function tenantRuntimeQueriesAreRecordedByDatabaseProfilerCollector(): void
    {
        $databaseConfig = [
            'connections' => [
                'default' => 'tenant',
                'tenant'  => [
                    'dsn' => 'sqlite:///:memory:',
                ],
            ],
        ];

        $databaseCollector    = new DatabaseProfilerCollector();
        $multiTenantCollector = new MultiTenantProfilerCollector();
        $registry             = new ProfilerRegistry();

        $registry->addCollector($databaseCollector);
        $registry->addCollector($multiTenantCollector);

        $profiler = new Profiler(registry: $registry);

        $manager = new TenantAwareConnectionManager(
            baseManager: new ConnectionManager(new DatabaseFactory($databaseConfig)),
            config: new Config([['database' => $databaseConfig]]),
            profiler: $profiler,
            databaseProfilerCollector: $databaseCollector,
            profilerCollector: $multiTenantCollector,
        );

        $profiler->startTrace('tenant-runtime-test');

        $manager->activate('sqlite:///:memory:');
        $manager->connection('tenant')->execute('CREATE TABLE products (id INTEGER PRIMARY KEY)');
        $manager->deactivate();

        $trace = $profiler->finishTrace();

        $sections = $trace?->toArray()['sections'] ?? [];
        $database = $sections['database'] ?? [];
        $queries  = $database['queries'] ?? [];

        $this->assertSame(1, $database['summary']['queries'] ?? null);
        $this->assertSame('tenant', $queries[0]['connection'] ?? null);
        $this->assertSame('sqlite', $queries[0]['driver'] ?? null);

        $multiTenant = $sections['multi_tenant'] ?? [];
        $events      = array_column($multiTenant['events'] ?? [], 'event');

        $this->assertContains('tenant.connection.activate', $events);
        $this->assertContains('tenant.connection.create', $events);
        $this->assertContains('tenant.connection.deactivate', $events);
    }
}
