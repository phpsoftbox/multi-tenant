<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Cli;

use PhpSoftBox\CliApp\Command\HandlerInterface;
use PhpSoftBox\CliApp\Response;
use PhpSoftBox\CliApp\Runner\RunnerInterface;
use PhpSoftBox\MultiTenant\Context\TenantContext;
use PhpSoftBox\MultiTenant\Context\TenantRuntimeScope;
use PhpSoftBox\MultiTenant\Tenant\Runtime\TenantRuntimeExecutor;
use PhpSoftBox\MultiTenant\Tenant\TenantSelector;
use PhpSoftBox\Queue\QueueJob;
use PhpSoftBox\Queue\QueueJobHandlerInterface;
use PhpSoftBox\Queue\Worker;
use Throwable;

use function is_int;
use function is_string;

final readonly class TenantQueueTenantRunHandler implements HandlerInterface
{
    public function __construct(
        private Worker $worker,
        private QueueJobHandlerInterface $handler,
        private TenantSelector $selector,
        private TenantRuntimeExecutor $runtime,
    ) {
    }

    public function run(RunnerInterface $runner): int|Response
    {
        $tenant = $runner->request()->option('tenant', 'all');
        if (!is_string($tenant) || $tenant === '') {
            $runner->io()->writeln('Некорректный параметр --tenant.', 'error');

            return Response::FAILURE;
        }

        $maxJobs = $runner->request()->option('max-jobs', 0);
        if (!is_int($maxJobs) || $maxJobs < 0) {
            $runner->io()->writeln('Некорректный параметр --max-jobs.', 'error');

            return Response::FAILURE;
        }

        try {
            $tenants = $this->selector->select($tenant, true);
        } catch (Throwable $exception) {
            $runner->io()->writeln($exception->getMessage(), 'error');

            return Response::FAILURE;
        }

        if ($tenants === []) {
            $runner->io()->writeln('Не найдено tenant для tenant queue режима.', 'warning');

            return Response::SUCCESS;
        }

        $processedTotal = 0;
        foreach ($tenants as $item) {
            try {
                $processed = $this->runtime->run(
                    $item,
                    fn (TenantContext $context): mixed => $this->worker->run(
                        fn (mixed $payload, QueueJob $job): mixed => $this->handler->handle($payload, $job),
                        $maxJobs,
                    ),
                    TenantRuntimeScope::Queue,
                );
                $processed = is_int($processed) ? $processed : 0;
            } catch (Throwable $exception) {
                $runner->io()->writeln(
                    '[tenant:' . $item->id . '] queue tenant mode error: ' . $exception->getMessage(),
                    'error',
                );

                return Response::FAILURE;
            }

            $processedTotal += $processed;

            $runner->io()->writeln(
                '[tenant:' . $item->id . '] queue tenant mode processed jobs: ' . $processed,
                'info',
            );
        }

        $runner->io()->writeln(
            'Queue tenant mode total processed jobs: ' . $processedTotal,
            'success',
        );

        return Response::SUCCESS;
    }
}
