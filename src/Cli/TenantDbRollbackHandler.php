<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Cli;

use PhpSoftBox\CliApp\Command\HandlerInterface;
use PhpSoftBox\CliApp\Response;
use PhpSoftBox\CliApp\Runner\RunnerInterface;
use PhpSoftBox\MultiTenant\Database\TenantDatabaseMigrationService;
use PhpSoftBox\MultiTenant\Tenant\TenantSelector;
use Throwable;

use function count;
use function is_int;
use function is_string;

final readonly class TenantDbRollbackHandler implements HandlerInterface
{
    public function __construct(
        private TenantSelector $selector,
        private TenantDatabaseMigrationService $migrations,
    ) {
    }

    public function run(RunnerInterface $runner): int|Response
    {
        $tenant = $runner->request()->option('tenant', 'all');
        if (!is_string($tenant) || $tenant === '') {
            $runner->io()->writeln('Некорректный параметр --tenant.', 'error');

            return Response::FAILURE;
        }

        $path = $runner->request()->option('path', null);
        if ($path !== null && (!is_string($path) || $path === '')) {
            $runner->io()->writeln('Некорректный параметр --path.', 'error');

            return Response::FAILURE;
        }

        $steps = $runner->request()->option('steps', 1);
        if (!is_int($steps) || $steps < 1) {
            $runner->io()->writeln('Некорректный параметр --steps.', 'error');

            return Response::FAILURE;
        }

        $failFast = $runner->request()->option('fail-fast', false) === true;

        try {
            $tenants = $this->selector->select($tenant, true);
        } catch (Throwable $exception) {
            $runner->io()->writeln($exception->getMessage(), 'error');

            return Response::FAILURE;
        }

        if ($tenants === []) {
            $runner->io()->writeln('Не найдено tenant для отката миграций.', 'warning');

            return Response::SUCCESS;
        }

        $errors = 0;
        foreach ($tenants as $item) {
            $runner->io()->writeln(
                '[tenant:' . $item->id . '] rollback, connection=' . $item->databaseConnection . ', steps=' . $steps,
                'info',
            );

            try {
                $rolledBack = $this->migrations->rollback($item->databaseConnection, $steps, $path);
                $runner->io()->writeln(
                    '[tenant:' . $item->id . '] откатано миграций: ' . count($rolledBack),
                    'success',
                );
            } catch (Throwable $exception) {
                $errors++;
                $runner->io()->writeln(
                    '[tenant:' . $item->id . '] ошибка: ' . $exception->getMessage(),
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
