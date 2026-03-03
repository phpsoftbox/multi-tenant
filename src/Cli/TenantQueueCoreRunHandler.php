<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Cli;

use PhpSoftBox\CliApp\Command\HandlerInterface;
use PhpSoftBox\CliApp\Response;
use PhpSoftBox\CliApp\Runner\RunnerInterface;
use PhpSoftBox\MultiTenant\Context\TenantContext;
use PhpSoftBox\MultiTenant\Context\TenantRuntimeScope;
use PhpSoftBox\MultiTenant\Provision\Queue\TenantProvisionQueueJobHandler;
use PhpSoftBox\MultiTenant\Tenant\Runtime\TenantRuntimeExecutor;
use PhpSoftBox\MultiTenant\Tenant\TenantSelector;
use PhpSoftBox\Queue\QueueJob;
use PhpSoftBox\Queue\QueueJobHandlerInterface;
use PhpSoftBox\Queue\Worker;
use RuntimeException;
use Throwable;

use function array_key_exists;
use function is_array;
use function is_int;
use function is_string;
use function trim;

final readonly class TenantQueueCoreRunHandler implements HandlerInterface
{
    public function __construct(
        private Worker $worker,
        private QueueJobHandlerInterface $handler,
        private TenantSelector $selector,
        private TenantRuntimeExecutor $runtime,
        private ?TenantProvisionQueueJobHandler $tenantProvisionJobs = null,
    ) {
    }

    public function run(RunnerInterface $runner): int|Response
    {
        $maxJobs = $runner->request()->option('max-jobs', 0);
        if (!is_int($maxJobs) || $maxJobs < 0) {
            $runner->io()->writeln('Некорректный параметр --max-jobs.', 'error');

            return Response::FAILURE;
        }

        try {
            $processed = $this->worker->run(
                fn (mixed $payload, QueueJob $job): mixed => $this->handleCorePayload($payload, $job),
                $maxJobs,
            );
        } catch (Throwable $exception) {
            $runner->io()->writeln('Queue core mode error: ' . $exception->getMessage(), 'error');

            return Response::FAILURE;
        }

        $runner->io()->writeln('Queue core mode processed jobs: ' . $processed, 'success');

        return Response::SUCCESS;
    }

    private function handleCorePayload(mixed $payload, QueueJob $job): void
    {
        if ($this->tenantProvisionJobs !== null && $this->tenantProvisionJobs->supports($payload)) {
            $this->tenantProvisionJobs->handle($payload);

            return;
        }

        $tenantId     = null;
        $innerPayload = $payload;

        if (is_array($payload)) {
            $candidate = $payload['tenant_id'] ?? ($payload['tenant'] ?? null);
            if (is_string($candidate) && trim($candidate) !== '') {
                $tenantId = trim($candidate);
            }

            if (array_key_exists('payload', $payload)) {
                $innerPayload = $payload['payload'];
            } elseif (array_key_exists('data', $payload)) {
                $innerPayload = $payload['data'];
            }
        }

        if ($this->tenantProvisionJobs !== null && $this->tenantProvisionJobs->supports($innerPayload)) {
            $this->tenantProvisionJobs->handle($innerPayload);

            return;
        }

        if ($tenantId === null) {
            throw new RuntimeException('Core queue payload должен содержать tenant_id.');
        }

        $selected = $this->selector->select($tenantId, true);
        if ($selected === []) {
            throw new RuntimeException('Tenant не найден для queue payload: ' . $tenantId);
        }

        $tenant = $selected[0];
        $this->runtime->run(
            $tenant,
            fn (TenantContext $context): mixed => $this->handler->handle($innerPayload, $job),
            TenantRuntimeScope::Queue,
        );
    }
}
