<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Cli;

use PhpSoftBox\CliApp\Command\HandlerInterface;
use PhpSoftBox\CliApp\Response;
use PhpSoftBox\CliApp\Runner\RunnerInterface;
use PhpSoftBox\Config\Config;
use PhpSoftBox\MultiTenant\Database\TenantDatabaseProvisionService;
use PhpSoftBox\MultiTenant\Tenant\TenantSelector;
use Throwable;

use function count;
use function is_string;

final readonly class TenantDbProvisionHandler implements HandlerInterface
{
    public function __construct(
        private TenantSelector $selector,
        private TenantDatabaseProvisionService $provision,
        private Config $config,
    ) {
    }

    public function run(RunnerInterface $runner): int|Response
    {
        $tenant = $runner->request()->option('tenant', 'all');
        if (!is_string($tenant) || $tenant === '') {
            $runner->io()->writeln('Некорректный параметр --tenant.', 'error');

            return Response::FAILURE;
        }

        $template = $runner->request()->option('template', null);
        if ($template !== null && (!is_string($template) || $template === '')) {
            $runner->io()->writeln('Некорректный параметр --template.', 'error');

            return Response::FAILURE;
        }

        if ($template === null || $template === '') {
            $template = $this->config->get('tenancy.provision.template_tenant', null);
        }

        if (!is_string($template) || $template === '') {
            $runner->io()->writeln(
                'Не указан template tenant. Передайте --template=<id> или настройте tenancy.provision.template_tenant.',
                'error',
            );

            return Response::FAILURE;
        }

        $migrationsTable = $runner->request()->option('migrations-table', 'migrations');
        if (!is_string($migrationsTable) || $migrationsTable === '') {
            $runner->io()->writeln('Некорректный параметр --migrations-table.', 'error');

            return Response::FAILURE;
        }

        $dropExisting = $runner->request()->option('drop-existing', false) === true;
        $failFast     = $runner->request()->option('fail-fast', false) === true;

        try {
            $targets       = $this->selector->select($tenant, true);
            $templateItems = $this->selector->select($template, false);
        } catch (Throwable $exception) {
            $runner->io()->writeln($exception->getMessage(), 'error');

            return Response::FAILURE;
        }

        if (count($templateItems) !== 1) {
            $runner->io()->writeln('Template должен указывать ровно на один tenant.', 'error');

            return Response::FAILURE;
        }

        $templateTenant = $templateItems[0];
        if (!$templateTenant->enabled) {
            $runner->io()->writeln('Template tenant отключен: ' . $templateTenant->id, 'error');

            return Response::FAILURE;
        }

        if ($targets === []) {
            $runner->io()->writeln('Не найдено tenant для provisioning.', 'warning');

            return Response::SUCCESS;
        }

        $errors = 0;
        foreach ($targets as $targetTenant) {
            if ($targetTenant->id === $templateTenant->id) {
                $runner->io()->writeln(
                    '[tenant:' . $targetTenant->id . '] пропуск: target совпадает с template.',
                    'warning',
                );
                continue;
            }

            $runner->io()->writeln(
                '[tenant:' . $targetTenant->id . '] provision from template=' . $templateTenant->id
                . ', target_connection=' . $targetTenant->databaseConnection
                . ', drop_existing=' . ($dropExisting ? '1' : '0'),
                'info',
            );

            try {
                $this->provision->provision(
                    templateConnection: $templateTenant->databaseConnection,
                    targetConnection: $targetTenant->databaseConnection,
                    migrationsTable: $migrationsTable,
                    dropExisting: $dropExisting,
                );

                $runner->io()->writeln(
                    '[tenant:' . $targetTenant->id . '] provision завершен.',
                    'success',
                );
            } catch (Throwable $exception) {
                $errors++;
                $runner->io()->writeln(
                    '[tenant:' . $targetTenant->id . '] ошибка: ' . $exception->getMessage(),
                    'error',
                );

                if ($failFast) {
                    return Response::FAILURE;
                }
            }
        }

        return $errors === 0 ? Response::SUCCESS : Response::FAILURE;
    }
}
