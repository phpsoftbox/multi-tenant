<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Cli\Telegram;

use PhpSoftBox\CliApp\Command\HandlerInterface;
use PhpSoftBox\CliApp\Response;
use PhpSoftBox\CliApp\Runner\RunnerInterface;
use PhpSoftBox\MultiTenant\Telegram\TenantTelegramBotRegistryFactory;
use PhpSoftBox\MultiTenant\Tenant\Runtime\TenantRuntimeExecutor;
use PhpSoftBox\MultiTenant\Tenant\TenantSelector;
use PhpSoftBox\Telegram\Bot\TelegramBotRegistry;
use PhpSoftBox\Telegram\Cli\TelegramPollHandler as BaseTelegramPollHandler;
use Throwable;

use function count;
use function in_array;
use function is_string;
use function trim;

final readonly class TelegramPollScopeHandler implements HandlerInterface
{
    public function __construct(
        private TelegramBotRegistry $coreRegistry,
        private TenantSelector $selector,
        private TenantRuntimeExecutor $runtime,
        private TenantTelegramBotRegistryFactory $tenantRegistryFactory,
    ) {
    }

    public function run(RunnerInterface $runner): int|Response
    {
        $scope = $this->readScope($runner);
        if ($scope === null) {
            return Response::FAILURE;
        }

        $once         = $runner->request()->option('once', false) === true;
        $tenantOption = $this->readTenantOption($runner);
        if ($tenantOption === null) {
            return Response::FAILURE;
        }

        if ($scope === 'all' && !$once) {
            $runner->io()->writeln('Для --scope=all у telegram:poll обязательно укажите --once.', 'error');

            return Response::FAILURE;
        }

        $errors = 0;

        if ($scope === 'core' || $scope === 'all') {
            $result = new BaseTelegramPollHandler($this->coreRegistry)->run($runner);

            if ($this->exitCode($result) !== Response::SUCCESS) {
                $errors++;
            }
        }

        if ($scope === 'tenant' || $scope === 'all') {
            $tenantCode = $this->runForTenants($runner, $tenantOption, $once);
            if ($tenantCode !== Response::SUCCESS) {
                $errors++;
            }
        }

        return $errors === 0 ? Response::SUCCESS : Response::FAILURE;
    }

    private function runForTenants(RunnerInterface $runner, string $tenantOption, bool $once): int
    {
        try {
            $tenants = $this->selector->select($tenantOption, true);
        } catch (Throwable $exception) {
            $runner->io()->writeln($exception->getMessage(), 'error');

            return Response::FAILURE;
        }

        if ($tenants === []) {
            $runner->io()->writeln('Не найдено tenant для telegram:poll. Tenant polling пропущен.', 'info');

            return Response::SUCCESS;
        }

        if (count($tenants) > 1 && !$once) {
            $runner->io()->writeln(
                'Для нескольких tenant в telegram:poll используйте --once или укажите конкретный --tenant.',
                'error',
            );

            return Response::FAILURE;
        }

        $errors = 0;

        foreach ($tenants as $tenant) {
            $runner->io()->writeln('[tenant:' . $tenant->id . '] telegram:poll start', 'info');

            try {
                $result = $this->runtime->run($tenant, function () use ($runner, $tenant) {
                    $registry = $this->tenantRegistryFactory->create($tenant);
                    if ($registry->names() === []) {
                        $runner->io()->writeln('[tenant:' . $tenant->id . '] не настроены telegram bots.', 'warning');

                        return Response::SUCCESS;
                    }

                    return new BaseTelegramPollHandler($registry)->run($runner);
                });
            } catch (Throwable $exception) {
                $runner->io()->writeln('[tenant:' . $tenant->id . '] ' . $exception->getMessage(), 'error');
                $errors++;
                continue;
            }

            if ($this->exitCode($result) !== Response::SUCCESS) {
                $errors++;
            }
        }

        return $errors === 0 ? Response::SUCCESS : Response::FAILURE;
    }

    private function readScope(RunnerInterface $runner): ?string
    {
        $scope = $runner->request()->option('scope', 'core');
        if (!is_string($scope)) {
            $runner->io()->writeln('Параметр --scope должен быть строкой.', 'error');

            return null;
        }

        $scope = trim($scope);
        if (!in_array($scope, ['core', 'tenant', 'all'], true)) {
            $runner->io()->writeln('Допустимые значения --scope: core|tenant|all.', 'error');

            return null;
        }

        return $scope;
    }

    private function readTenantOption(RunnerInterface $runner): ?string
    {
        $tenantOption = $runner->request()->option('tenant', 'all');
        if (!is_string($tenantOption) || trim($tenantOption) === '') {
            $runner->io()->writeln('Параметр --tenant должен быть непустой строкой.', 'error');

            return null;
        }

        return trim($tenantOption);
    }

    private function exitCode(int|Response $result): int
    {
        return $result instanceof Response ? $result->code : $result;
    }
}
