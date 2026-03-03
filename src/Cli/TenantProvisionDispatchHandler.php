<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Cli;

use PhpSoftBox\CliApp\Command\HandlerInterface;
use PhpSoftBox\CliApp\Response;
use PhpSoftBox\CliApp\Runner\RunnerInterface;
use PhpSoftBox\MultiTenant\Provision\Queue\TenantProvisionQueueDispatcher;
use PhpSoftBox\MultiTenant\Provision\TenantProvisionPayload;
use PhpSoftBox\MultiTenant\Tenant\TenantSelector;
use Throwable;

use function is_int;
use function is_string;
use function trim;

final readonly class TenantProvisionDispatchHandler implements HandlerInterface
{
    public function __construct(
        private TenantSelector $selector,
        private TenantProvisionQueueDispatcher $dispatcher,
    ) {
    }

    public function run(RunnerInterface $runner): int|Response
    {
        $tenant = $runner->request()->option('tenant', 'all');
        if (!is_string($tenant) || trim($tenant) === '') {
            $runner->io()->writeln('Некорректный параметр --tenant.', 'error');

            return Response::FAILURE;
        }

        $priority = $runner->request()->option('priority', 0);
        if (!is_int($priority)) {
            $runner->io()->writeln('Некорректный параметр --priority.', 'error');

            return Response::FAILURE;
        }

        try {
            $tenants = $this->selector->select(trim($tenant), true);
        } catch (Throwable $exception) {
            $runner->io()->writeln($exception->getMessage(), 'error');

            return Response::FAILURE;
        }

        if ($tenants === []) {
            $runner->io()->writeln('Не найдено tenant для dispatch provisioning.', 'warning');

            return Response::SUCCESS;
        }

        $templateTenantId  = $this->normalizeOption($runner->request()->option('template', null));
        $ownerPhone        = $this->normalizeOption($runner->request()->option('owner-phone', null));
        $ownerName         = $this->normalizeOption($runner->request()->option('owner-name', null));
        $ownerEmail        = $this->normalizeOption($runner->request()->option('owner-email', null));
        $confirmOwnerPhone = $runner->request()->option('no-confirm-phone', false) !== true;

        foreach ($tenants as $item) {
            $payload = new TenantProvisionPayload(
                tenantId: $item->id,
                templateTenantId: $templateTenantId,
                ownerPhone: $ownerPhone,
                ownerName: $ownerName,
                ownerEmail: $ownerEmail,
                confirmOwnerPhone: $confirmOwnerPhone,
            );

            try {
                $jobId = $this->dispatcher->dispatch($payload, $priority);
            } catch (Throwable $exception) {
                $runner->io()->writeln(
                    '[tenant:' . $item->id . '] ошибка dispatch: ' . $exception->getMessage(),
                    'error',
                );

                return Response::FAILURE;
            }

            $runner->io()->writeln(
                '[tenant:' . $item->id . '] provision job queued: ' . $jobId,
                'success',
            );
        }

        return Response::SUCCESS;
    }

    private function normalizeOption(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }
}
