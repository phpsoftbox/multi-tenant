<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Database;

use PhpSoftBox\Database\Connection\ConnectionManagerInterface;
use PhpSoftBox\Database\Migrations\FileMigrationLoader;
use PhpSoftBox\Database\Migrations\MigrationPlan;
use PhpSoftBox\Database\Migrations\MigrationRepositoryInterface;
use PhpSoftBox\Database\Migrations\MigrationRunner;
use PhpSoftBox\Database\Migrations\MigrationsConfig;
use PhpSoftBox\Database\Migrations\SqlMigrationRepository;
use RuntimeException;

use function getcwd;
use function is_dir;
use function is_string;
use function rtrim;
use function str_starts_with;

final readonly class TenantDatabaseMigrationService
{
    public function __construct(
        private ConnectionManagerInterface $connections,
        private MigrationsConfig $config,
        private ?MigrationRepositoryInterface $repository = null,
    ) {
    }

    /**
     * @return list<string>
     */
    public function migrate(string $connectionName, ?string $relativePath = null): array
    {
        $plan   = $this->createPlan($connectionName, $relativePath);
        $runner = $this->createRunner($connectionName);

        return $runner->migrate($plan);
    }

    /**
     * @return list<string>
     */
    public function rollback(string $connectionName, int $steps = 1, ?string $relativePath = null): array
    {
        if ($steps < 1) {
            throw new RuntimeException('steps должен быть >= 1.');
        }

        $plan   = $this->createPlan($connectionName, $relativePath);
        $runner = $this->createRunner($connectionName);

        return $runner->rollback($plan, $steps);
    }

    private function createRunner(string $connectionName): MigrationRunner
    {
        return new MigrationRunner(
            connections: $this->connections,
            repository: $this->repository ?? new SqlMigrationRepository(),
            connectionName: $connectionName,
        );
    }

    private function createPlan(string $connectionName, ?string $relativePath): MigrationPlan
    {
        $paths  = $this->resolvePaths($connectionName, $relativePath);
        $loader = new FileMigrationLoader();
        $plan   = new MigrationPlan();
        $known  = [];

        foreach ($paths as $path) {
            if (!is_dir($path)) {
                throw new RuntimeException('Директория миграций не найдена: ' . $path);
            }

            foreach ($loader->load($path, recursive: false) as $item) {
                if (isset($known[$item['id']])) {
                    throw new RuntimeException('Дублирующаяся миграция: ' . $item['id']);
                }

                $known[$item['id']] = true;
                $plan->add($item['id'], $item['migration']);
            }
        }

        return $plan;
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
