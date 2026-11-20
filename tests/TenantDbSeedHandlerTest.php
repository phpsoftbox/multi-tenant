<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests;

use PhpSoftBox\CliApp\CliApp;
use PhpSoftBox\CliApp\Command\InMemoryCommandRegistry;
use PhpSoftBox\CliApp\Io\IoInterface;
use PhpSoftBox\CliApp\Response;
use PhpSoftBox\Config\Config;
use PhpSoftBox\Database\Configurator\DatabaseFactory;
use PhpSoftBox\Database\Connection\ConnectionManager;
use PhpSoftBox\Database\Contracts\ConnectionInterface;
use PhpSoftBox\Database\Seed\SeederInterface;
use PhpSoftBox\Database\Seed\SeedRunner;
use PhpSoftBox\MultiTenant\Cli\TenantCommandProvider;
use PhpSoftBox\MultiTenant\Cli\TenantDbSeedHandler;
use PhpSoftBox\MultiTenant\Database\TenantAwareConnectionManager;
use PhpSoftBox\MultiTenant\Database\TenantDsnResolver;
use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;
use PhpSoftBox\MultiTenant\Tests\Support\TestTenantProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

use function sys_get_temp_dir;
use function tempnam;
use function unlink;

#[CoversClass(TenantDbSeedHandler::class)]
#[CoversClass(TenantCommandProvider::class)]
#[CoversMethod(TenantDbSeedHandler::class, 'run')]
#[CoversMethod(TenantAwareConnectionManager::class, 'tenantWrite')]
final class TenantDbSeedHandlerTest extends TestCase
{
    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            unlink($file);
        }
    }

    /**
     * Проверим, что ID отключённого арендатора выбирает только его БД и после запуска восстанавливается прежний DSN.
     *
     * @see TenantDbSeedHandler::run()
     * @see TenantAwareConnectionManager::tenantWrite()
     */
    #[Test]
    public function seedsOnlySelectedTenantAndRestoresPreviousScope(): void
    {
        $seeder = $this->createMock(SeederInterface::class);
        $seeder->expects($this->once())->method('run')->willReturnCallback(
            static function (ConnectionInterface $connection): void {
                $connection->query()->insert('settings', ['id' => 1])->execute();
            },
        );

        [$app, $connections, $base, $other] = $this->setupApp($seeder);
        $connections->activate($other);

        self::assertSame(Response::SUCCESS, $app->runCommand('tenant:db:seed', ['settings', '--tenant=acme'])->code);
        self::assertSame($other, $connections->activeDsn());
        self::assertSame([['id' => 1]], $base->write('acme_db')->fetchAll('SELECT id FROM settings'));
        self::assertSame([], $base->write('other_db')->fetchAll('SELECT id FROM settings'));
        self::assertSame([], $base->write('core')->fetchAll('SELECT id FROM settings'));
        self::assertSame([], $base->connection('tenant')->fetchAll('SELECT id FROM settings'));
    }

    /**
     * Проверим, что неправильный алиас подключения арендатора не приводит к записи в общую БД.
     *
     * @see TenantDbSeedHandler::run()
     * @see TenantAwareConnectionManager::tenantWrite()
     */
    #[Test]
    public function rejectsConnectionOutsideTenantScope(): void
    {
        $seeder = $this->createMock(SeederInterface::class);
        $seeder->expects($this->never())->method('run');

        [$app, $connections, $base] = $this->setupApp($seeder, 'core');

        self::assertSame(Response::FAILURE, $app->runCommand('tenant:db:seed', ['settings', '--tenant=acme'])->code);
        self::assertNull($connections->activeDsn());
        self::assertSame([], $base->write('core')->fetchAll('SELECT id FROM settings'));
    }

    /**
     * Проверим, что команда принимает один существующий ID и отвергает массовый выбор и имя арендатора.
     *
     * @see TenantDbSeedHandler::run()
     * @see TenantCommandProvider::register()
     */
    #[Test]
    #[DataProvider('invalidTenantArguments')]
    public function rejectsInvalidTenantSelection(array $argv): void
    {
        $seeder = $this->createMock(SeederInterface::class);
        $seeder->expects($this->never())->method('run');
        [$app, $connections] = $this->setupApp($seeder);

        self::assertNotSame(Response::SUCCESS, $app->runCommand('tenant:db:seed', $argv)->code);
        self::assertNull($connections->activeDsn());
    }

    public static function invalidTenantArguments(): iterable
    {
        yield 'без ID' => [['settings']];
        yield 'все' => [['settings', '--tenant=all']];
        yield 'список' => [['settings', '--tenant=acme,other']];
        yield 'имя вместо ID' => [['settings', '--tenant=ACME Ltd']];
    }

    /** @return array{CliApp, TenantAwareConnectionManager, ConnectionManager, string} */
    private function setupApp(SeederInterface $seeder, string $tenantAlias = 'tenant'): array
    {
        $acme     = $this->sqliteDsn();
        $other    = $this->sqliteDsn();
        $core     = $this->sqliteDsn();
        $template = $this->sqliteDsn();

        $database = [
            'connections' => [
                'default'      => 'core',
                'core'         => ['dsn' => $core],
                'tenant'       => ['dsn' => $template],
                'tenant.write' => ['dsn' => $core],
                'acme_db'      => ['dsn' => $acme],
                'other_db'     => ['dsn' => $other],
            ],
        ];

        $base = new ConnectionManager(new DatabaseFactory($database));

        foreach (['core', 'tenant', 'acme_db', 'other_db'] as $name) {
            $base->connection($name)->execute('CREATE TABLE settings (id INTEGER PRIMARY KEY)');
        }

        $config = new Config([['database' => $database]]);

        $connections = new TenantAwareConnectionManager($base, $config);
        $tenants     = new TestTenantProvider([
            new TenantDefinition('acme', 'ACME Ltd', null, $tenantAlias, enabled: false, data: ['dsn' => $acme]),
            new TenantDefinition('other', 'Other Ltd', null, 'tenant', data: ['dsn' => $other]),
        ]);

        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturnMap([
            ['settings.seeder', $seeder],
            [TenantDbSeedHandler::class, new TenantDbSeedHandler(
                $tenants,
                $connections,
                new TenantDsnResolver($config),
                new SeedRunner($container, ['settings' => ['service' => 'settings.seeder', 'scope' => 'tenant']]),
            )],
        ]);

        $registry = new InMemoryCommandRegistry(withDefaultCommands: false);

        new TenantCommandProvider()->register($registry);

        return [new CliApp($registry, $this->createStub(IoInterface::class), $container), $connections, $base, $other];
    }

    private function sqliteDsn(): string
    {
        $file = tempnam(sys_get_temp_dir(), 'psb-seed-');
        self::assertIsString($file);
        $this->files[] = $file;

        return 'sqlite:///' . $file;
    }
}
