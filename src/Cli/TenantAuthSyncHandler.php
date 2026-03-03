<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Cli;

use PhpSoftBox\Auth\Authorization\DefaultPermissionNameFormatter;
use PhpSoftBox\Auth\Authorization\PhpFileRoleDefinitionProvider;
use PhpSoftBox\Auth\Authorization\RoleSynchronizer;
use PhpSoftBox\Auth\Authorization\Store\Database\DatabasePermissionStore;
use PhpSoftBox\Auth\Authorization\Store\Database\DatabaseRolePermissionStore;
use PhpSoftBox\Auth\Authorization\Store\Database\DatabaseRoleStore;
use PhpSoftBox\CliApp\Command\HandlerInterface;
use PhpSoftBox\CliApp\Response;
use PhpSoftBox\CliApp\Runner\RunnerInterface;
use PhpSoftBox\Config\Config;
use PhpSoftBox\Database\Connection\ConnectionManagerInterface;
use PhpSoftBox\MultiTenant\Tenant\Runtime\TenantRuntimeExecutor;
use PhpSoftBox\MultiTenant\Tenant\TenantSelector;
use RuntimeException;
use Throwable;

use function array_map;
use function count;
use function implode;
use function in_array;
use function is_array;
use function is_string;
use function trim;

final readonly class TenantAuthSyncHandler implements HandlerInterface
{
    public function __construct(
        private Config $config,
        private ConnectionManagerInterface $connections,
        private TenantSelector $selector,
        private TenantRuntimeExecutor $runtime,
    ) {
    }

    public function run(RunnerInterface $runner): int|Response
    {
        $scope = $runner->request()->option('scope', 'all');
        if (!is_string($scope)) {
            $runner->io()->writeln('Параметр --scope должен быть строкой.', 'error');

            return Response::FAILURE;
        }

        $scope = trim($scope);
        if (!in_array($scope, ['all', 'core', 'tenant'], true)) {
            $runner->io()->writeln('Допустимые значения --scope: all|core|tenant.', 'error');

            return Response::FAILURE;
        }

        $tenantOption = $runner->request()->option('tenant', 'all');
        if (!is_string($tenantOption) || trim($tenantOption) === '') {
            $runner->io()->writeln('Параметр --tenant должен быть непустой строкой.', 'error');

            return Response::FAILURE;
        }

        $dryRun = $runner->request()->option('dry-run', false) === true;

        $errors = 0;

        if ($scope === 'all' || $scope === 'core') {
            if (!$this->syncCore($runner, $dryRun)) {
                $errors++;
            }
        }

        if ($scope === 'all' || $scope === 'tenant') {
            if (!$this->syncTenants($runner, $tenantOption, $dryRun)) {
                $errors++;
            }
        }

        return $errors === 0 ? Response::SUCCESS : Response::FAILURE;
    }

    private function syncCore(RunnerInterface $runner, bool $dryRun): bool
    {
        try {
            $paths = $this->coreDefinitionPaths();
        } catch (Throwable $exception) {
            $runner->io()->writeln($exception->getMessage(), 'error');

            return false;
        }

        $connection = (string) $this->config->get('auth.permissions.connection', 'default');
        $runner->io()->writeln('[core] sync start, connection=' . $connection, 'info');

        return $this->syncConnection(
            connection: $connection,
            paths: $paths,
            dryRun: $dryRun,
            runner: $runner,
            scopeLabel: 'core',
        );
    }

    private function syncTenants(RunnerInterface $runner, string $tenantOption, bool $dryRun): bool
    {
        try {
            $paths = $this->tenantDefinitionPaths();
        } catch (Throwable $exception) {
            $runner->io()->writeln($exception->getMessage(), 'error');

            return false;
        }

        try {
            $tenants = $this->selector->select($tenantOption, true);
        } catch (Throwable $exception) {
            $runner->io()->writeln($exception->getMessage(), 'error');

            return false;
        }

        if ($tenants === []) {
            $runner->io()->writeln('Не найдено tenant для синхронизации ролей.', 'warning');

            return true;
        }

        $tenantConnection = (string) $this->config->get('tenancy.runtime.database.connection_alias', 'tenant');
        $allOk            = true;

        foreach ($tenants as $tenant) {
            $runner->io()->writeln('[tenant:' . $tenant->id . '] sync start', 'info');

            try {
                $ok = $this->runtime->run(
                    $tenant,
                    fn (mixed $context): bool => $this->syncConnection(
                        connection: $tenantConnection,
                        paths: $paths,
                        dryRun: $dryRun,
                        runner: $runner,
                        scopeLabel: 'tenant:' . $tenant->id,
                    ),
                );
            } catch (Throwable $exception) {
                $runner->io()->writeln('[tenant:' . $tenant->id . '] ' . $exception->getMessage(), 'error');
                $allOk = false;
                continue;
            }

            if (!$ok) {
                $allOk = false;
            }
        }

        return $allOk;
    }

    /**
     * @param list<string> $paths
     */
    private function syncConnection(
        string $connection,
        array $paths,
        bool $dryRun,
        RunnerInterface $runner,
        string $scopeLabel,
    ): bool {
        $tables    = (array) $this->config->get('auth.permissions.tables', []);
        $separator = (string) $this->config->get('auth.authorization.separator', '.');

        $sync = new RoleSynchronizer(
            definitions: new PhpFileRoleDefinitionProvider($paths),
            permissions: new DatabasePermissionStore(
                connections: $this->connections,
                connectionName: $connection,
                table: (string) ($tables['permissions'] ?? 'permissions'),
            ),
            roles: new DatabaseRoleStore(
                connections: $this->connections,
                connectionName: $connection,
                table: (string) ($tables['roles'] ?? 'roles'),
            ),
            rolePermissions: new DatabaseRolePermissionStore(
                connections: $this->connections,
                connectionName: $connection,
                table: (string) ($tables['role_permissions'] ?? 'role_permissions'),
            ),
            formatter: new DefaultPermissionNameFormatter($separator),
        );

        try {
            if ($dryRun) {
                $plan = $sync->plan();
                $runner->io()->writeln(
                    '[' . $scopeLabel . '] dry-run: +roles=' . count($plan->rolesToCreate)
                    . ', -roles=' . count($plan->rolesToDelete)
                    . ', +permissions=' . count($plan->permissionsToCreate)
                    . ', -permissions=' . count($plan->permissionsToDelete),
                    'info',
                );

                if ($plan->rolesToCreate !== []) {
                    $runner->io()->writeln('[' . $scopeLabel . '] create roles: ' . implode(', ', $plan->rolesToCreate), 'info');
                }
                if ($plan->rolesToDelete !== []) {
                    $runner->io()->writeln('[' . $scopeLabel . '] delete roles: ' . implode(', ', $plan->rolesToDelete), 'warning');
                }
                if ($plan->permissionsToCreate !== []) {
                    $runner->io()->writeln('[' . $scopeLabel . '] create permissions: ' . implode(', ', $plan->permissionsToCreate), 'info');
                }
                if ($plan->permissionsToDelete !== []) {
                    $runner->io()->writeln('[' . $scopeLabel . '] delete permissions: ' . implode(', ', $plan->permissionsToDelete), 'warning');
                }

                return true;
            }

            $sync->sync();
            $runner->io()->writeln('[' . $scopeLabel . '] roles synced.', 'success');

            return true;
        } catch (Throwable $exception) {
            $runner->io()->writeln('[' . $scopeLabel . '] ' . $exception->getMessage(), 'error');

            return false;
        }
    }

    /**
     * @return list<string>
     */
    private function coreDefinitionPaths(): array
    {
        $paths = $this->config->get('auth.authorization.core_paths', []);

        return $this->normalizePaths($paths, 'Не настроены пути для core roles (auth.authorization.core_paths).');
    }

    /**
     * @return list<string>
     */
    private function tenantDefinitionPaths(): array
    {
        $paths = $this->config->get('auth.authorization.tenant_paths', []);

        return $this->normalizePaths($paths, 'Не настроены пути для tenant roles (auth.authorization.tenant_paths).');
    }

    /**
     * @return list<string>
     */
    private function normalizePaths(mixed $raw, string $errorMessage): array
    {
        if (is_string($raw)) {
            $raw = [$raw];
        }

        if (!is_array($raw) || $raw === []) {
            throw new RuntimeException($errorMessage);
        }

        $out = [];
        foreach ($raw as $path) {
            if (!is_string($path)) {
                continue;
            }

            $path = trim($path);
            if ($path === '') {
                continue;
            }

            $out[] = $path;
        }

        if ($out === []) {
            throw new RuntimeException($errorMessage);
        }

        return array_map('strval', $out);
    }
}
