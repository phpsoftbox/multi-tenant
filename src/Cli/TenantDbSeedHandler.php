<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Cli;

use PhpSoftBox\CliApp\Command\HandlerInterface;
use PhpSoftBox\CliApp\Response;
use PhpSoftBox\CliApp\Runner\RunnerInterface;
use PhpSoftBox\Database\Connection\ConnectionManagerInterface;
use PhpSoftBox\Database\Contracts\ConnectionInterface;
use PhpSoftBox\Database\Seed\SeedRunner;
use PhpSoftBox\Database\Seed\SeedTarget;
use PhpSoftBox\MultiTenant\Contracts\TenantProviderInterface;
use PhpSoftBox\MultiTenant\Database\TenantAwareConnectionManager;
use PhpSoftBox\MultiTenant\Database\TenantDatabaseMigrationScope;
use PhpSoftBox\MultiTenant\Database\TenantDsnResolver;
use RuntimeException;
use Throwable;

use function is_string;
use function str_contains;
use function trim;

final readonly class TenantDbSeedHandler implements HandlerInterface
{
    public function __construct(
        private TenantProviderInterface $tenants,
        private ConnectionManagerInterface $connections,
        private TenantDsnResolver $dsnResolver,
        private SeedRunner $seeds,
    ) {
    }

    public function run(RunnerInterface $runner): int|Response
    {
        $scenario = $runner->request()->param('scenario');
        $id       = $runner->request()->option('tenant');

        if (!is_string($id) || ($id = trim($id)) === '' || $id === 'all' || str_contains($id, ',')) {
            $runner->io()->writeln('Укажите ID одного арендатора через --tenant.', 'error');

            return Response::FAILURE;
        }

        if (!is_string($scenario) || trim($scenario) === '') {
            $runner->io()->writeln('Укажите имя seed-сценария.', 'error');

            return Response::FAILURE;
        }

        try {
            $tenant = $this->tenants->findById($id);
            if ($tenant === null || $tenant->id !== $id) {
                throw new RuntimeException('Арендатор не найден: ' . $id);
            }

            $connections = $this->connections;
            if (!$connections instanceof TenantAwareConnectionManager) {
                throw new RuntimeException('Не настроен менеджер подключений арендаторов.');
            }

            $scope = new TenantDatabaseMigrationScope($this->dsnResolver, $connections);

            $scope->run($tenant, function (string $name) use ($connections, $scenario): void {
                $this->seeds->run(
                    $scenario,
                    SeedTarget::Tenant,
                    static fn (): ConnectionInterface => $connections->tenantWrite($name),
                );
            });
        } catch (Throwable $exception) {
            $runner->io()->writeln('[tenant:' . $id . '] Ошибка заполнения БД: ' . $exception->getMessage(), 'error');

            return Response::FAILURE;
        }

        $runner->io()->writeln('[tenant:' . $id . '] Применён seed-сценарий ' . $scenario . '.', 'success');

        return Response::SUCCESS;
    }
}
