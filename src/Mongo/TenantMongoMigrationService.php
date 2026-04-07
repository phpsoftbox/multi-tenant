<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Mongo;

use PhpSoftBox\MongoDb\Connection\MongoConnectionManagerInterface;
use PhpSoftBox\MongoDb\Migration\FileMigrationLoader;
use PhpSoftBox\MongoDb\Migration\MigrationInterface;
use PhpSoftBox\MongoDb\Migration\MigrationsConfig;
use PhpSoftBox\MongoDb\Migration\MigrationStateStoreInterface;
use PhpSoftBox\MongoDb\Migration\Migrator;
use PhpSoftBox\MongoDb\Migration\MongoMigrationStateStore;
use RuntimeException;

use function getcwd;
use function is_dir;
use function is_string;
use function rtrim;
use function str_starts_with;

final readonly class TenantMongoMigrationService
{
    public function __construct(
        private MongoConnectionManagerInterface $mongo,
        private MigrationsConfig $config,
        private ?MigrationStateStoreInterface $stateStore = null,
    ) {
    }

    /**
     * @return list<string>
     */
    public function migrate(string $connectionName, ?string $relativePath = null): array
    {
        $migrations = $this->loadMigrations($connectionName, $relativePath);
        $migrator   = $this->createMigrator();

        return $migrator->migrate($migrations, $connectionName);
    }

    /**
     * @return list<string>
     */
    public function rollback(string $connectionName, int $steps = 1, ?string $relativePath = null): array
    {
        if ($steps < 1) {
            throw new RuntimeException('steps должен быть >= 1.');
        }

        $migrations = $this->loadMigrations($connectionName, $relativePath);
        $migrator   = $this->createMigrator();

        return $migrator->rollback($migrations, $steps, $connectionName);
    }

    private function createMigrator(): Migrator
    {
        return new Migrator(
            mongo: $this->mongo,
            stateStore: $this->stateStore ?? new MongoMigrationStateStore($this->mongo),
        );
    }

    /**
     * @return list<MigrationInterface>
     */
    private function loadMigrations(string $connectionName, ?string $relativePath): array
    {
        $paths  = $this->resolvePaths($connectionName, $relativePath);
        $loader = new FileMigrationLoader();
        $known  = [];
        $out    = [];

        foreach ($paths as $path) {
            if (!is_dir($path)) {
                throw new RuntimeException('Директория миграций не найдена: ' . $path);
            }

            foreach ($loader->load($path, recursive: false) as $item) {
                if (isset($known[$item['id']])) {
                    throw new RuntimeException('Дублирующаяся миграция: ' . $item['id']);
                }

                $known[$item['id']] = true;
                $out[]              = $item['migration'];
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function resolvePaths(string $connectionName, ?string $relativePath): array
    {
        if ($relativePath !== null && (!is_string($relativePath) || $relativePath === '')) {
            throw new RuntimeException('Некорректный path.');
        }

        if ($relativePath !== null && str_starts_with($relativePath, '/')) {
            throw new RuntimeException('path должен быть относительным.');
        }

        $basePaths = $this->config->paths($connectionName);
        if ($basePaths === []) {
            throw new RuntimeException('Не настроены пути миграций для подключения: ' . $connectionName);
        }

        $paths = [];
        foreach ($basePaths as $basePath) {
            $path = $basePath;
            if ($relativePath !== null && $relativePath !== '') {
                $path = rtrim($basePath, '/') . '/' . $relativePath;
            }

            $normalized = $this->normalizePath($path);
            if ($normalized !== null) {
                $paths[] = $normalized;
            }
        }

        if ($paths === []) {
            throw new RuntimeException('Не найдены директории миграций.');
        }

        return $paths;
    }

    private function normalizePath(mixed $path): ?string
    {
        if (!is_string($path) || $path === '') {
            return null;
        }

        $path = rtrim($path, '/');
        if ($path === '') {
            return null;
        }

        if (!str_starts_with($path, '/')) {
            $cwd = getcwd();
            if ($cwd !== false) {
                $path = rtrim($cwd, '/') . '/' . $path;
            }
        }

        return $path;
    }
}
