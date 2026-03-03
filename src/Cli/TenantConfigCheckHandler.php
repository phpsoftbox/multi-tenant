<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Cli;

use PhpSoftBox\CliApp\Command\HandlerInterface;
use PhpSoftBox\CliApp\Response;
use PhpSoftBox\CliApp\Runner\RunnerInterface;
use PhpSoftBox\Config\Config;
use PhpSoftBox\Database\Connection\ConnectionManagerInterface;
use PhpSoftBox\MultiTenant\Tenant\Provider\ConfigTenantProvider;
use PhpSoftBox\MultiTenant\Tenant\Provider\ConsistentTenantProvider;
use PhpSoftBox\MultiTenant\Tenant\Provider\DatabaseTenantProvider;
use Throwable;

use function count;
use function in_array;
use function is_string;
use function strtolower;
use function trim;

final readonly class TenantConfigCheckHandler implements HandlerInterface
{
    public function __construct(
        private Config $config,
        private ConnectionManagerInterface $connections,
    ) {
    }

    public function run(RunnerInterface $runner): int|Response
    {
        $primaryInput = $runner->request()->option('primary', null);
        if ($primaryInput !== null && !is_string($primaryInput)) {
            $runner->io()->writeln('Некорректный параметр --primary.', 'error');

            return Response::FAILURE;
        }

        $primary = $primaryInput;
        if (!is_string($primary) || trim($primary) === '') {
            $primary = (string) $this->config->get('tenancy.providers.primary', 'database');
        }
        $primary = strtolower(trim($primary));

        if (!in_array($primary, ['database', 'config'], true)) {
            $runner->io()->writeln('Параметр --primary должен быть database или config.', 'error');

            return Response::FAILURE;
        }

        $connectionInput = $runner->request()->option('connection', null);
        if ($connectionInput !== null && !is_string($connectionInput)) {
            $runner->io()->writeln('Некорректный параметр --connection.', 'error');

            return Response::FAILURE;
        }

        $connectionName = $connectionInput;
        if (!is_string($connectionName) || trim($connectionName) === '') {
            $connectionName = (string) $this->config->get('tenancy.providers.database.connection', 'default');
        }
        $connectionName = trim($connectionName);
        if ($connectionName === '') {
            $runner->io()->writeln('Не удалось определить connection для DatabaseTenantProvider.', 'error');

            return Response::FAILURE;
        }

        $configProvider   = new ConfigTenantProvider($this->config);
        $databaseProvider = new DatabaseTenantProvider($this->connections, $connectionName);

        $primaryProvider = $primary === 'database' ? $databaseProvider : $configProvider;
        $secondary       = $primary === 'database' ? $configProvider : $databaseProvider;

        $runner->io()->writeln(
            'Проверка tenant parity: primary=' . $primary . ', db_connection=' . $connectionName,
            'info',
        );

        try {
            $consistent = new ConsistentTenantProvider(
                primary: $primaryProvider,
                secondary: $secondary,
                enforceParity: true,
            );

            $tenants = $consistent->all(false);
            $runner->io()->writeln('Tenant parity OK. Total tenants: ' . count($tenants), 'success');

            return Response::SUCCESS;
        } catch (Throwable $exception) {
            $runner->io()->writeln('Tenant parity FAILED: ' . $exception->getMessage(), 'error');

            return Response::FAILURE;
        }
    }
}
