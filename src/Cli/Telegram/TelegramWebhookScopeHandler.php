<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Cli\Telegram;

use PhpSoftBox\CliApp\Command\HandlerInterface;
use PhpSoftBox\CliApp\Request\Request;
use PhpSoftBox\CliApp\Response;
use PhpSoftBox\CliApp\Runner\RunnerInterface;
use PhpSoftBox\Config\Config;
use PhpSoftBox\MultiTenant\Telegram\TenantTelegramBotRegistryFactory;
use PhpSoftBox\MultiTenant\Tenant\Runtime\TenantRuntimeExecutor;
use PhpSoftBox\MultiTenant\Tenant\TenantSelector;
use PhpSoftBox\Telegram\Bot\TelegramBotRegistry;
use PhpSoftBox\Telegram\Cli\TelegramWebhookHandler as BaseTelegramWebhookHandler;
use Throwable;

use function in_array;
use function is_string;
use function trim;

final readonly class TelegramWebhookScopeHandler implements HandlerInterface
{
    public function __construct(
        private TelegramBotRegistry $coreRegistry,
        private TenantSelector $selector,
        private TenantRuntimeExecutor $runtime,
        private TenantTelegramBotRegistryFactory $tenantRegistryFactory,
        private Config $config,
    ) {
    }

    public function run(RunnerInterface $runner): int|Response
    {
        $scope = $this->readScope($runner);
        if ($scope === null) {
            return Response::FAILURE;
        }

        $tenantOption = $this->readTenantOption($runner);
        if ($tenantOption === null) {
            return Response::FAILURE;
        }

        $errors = 0;

        if ($scope === 'core' || $scope === 'all') {
            $runner->io()->writeln('[core] telegram:webhook start', 'info');

            $coreBaseUrl = trim((string) $this->config->get('app.admin_url', ''));
            $result      = $this->runWebhook($runner, $this->coreRegistry, $coreBaseUrl);
            if ($result !== Response::SUCCESS) {
                $errors++;
            }
        }

        if ($scope === 'tenant' || $scope === 'all') {
            $tenantResult = $this->runForTenants($runner, $tenantOption);
            if ($tenantResult !== Response::SUCCESS) {
                $errors++;
            }
        }

        return $errors === 0 ? Response::SUCCESS : Response::FAILURE;
    }

    private function runForTenants(RunnerInterface $runner, string $tenantOption): int
    {
        try {
            $tenants = $this->selector->select($tenantOption, true);
        } catch (Throwable $exception) {
            $runner->io()->writeln($exception->getMessage(), 'error');

            return Response::FAILURE;
        }

        if ($tenants === []) {
            $runner->io()->writeln('Не найдено tenant для telegram:webhook.', 'warning');

            return Response::SUCCESS;
        }

        $errors = 0;

        foreach ($tenants as $tenant) {
            $runner->io()->writeln('[tenant:' . $tenant->id . '] telegram:webhook start', 'info');

            try {
                $result = $this->runtime->run($tenant, function () use ($runner, $tenant) {
                    $registry = $this->tenantRegistryFactory->create($tenant);
                    if ($registry->names() === []) {
                        $runner->io()->writeln('[tenant:' . $tenant->id . '] не настроены telegram bots.', 'warning');

                        return Response::SUCCESS;
                    }

                    $baseUrl = $tenant->primaryDomain !== null && trim($tenant->primaryDomain) !== ''
                        ? 'https://' . trim($tenant->primaryDomain)
                        : '';

                    return $this->runWebhook($runner, $registry, $baseUrl);
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

    private function runWebhook(RunnerInterface $runner, TelegramBotRegistry $registry, string $fallbackBaseUrl): int
    {
        $request = $runner->request();

        $url     = (string) $request->option('url', '');
        $baseUrl = (string) $request->option('base-url', '');
        if (trim($url) === '' && trim($baseUrl) === '' && trim($fallbackBaseUrl) !== '') {
            $runner = $this->withOption($runner, 'base-url', $fallbackBaseUrl);
        }

        $result = new BaseTelegramWebhookHandler($registry)->run($runner);

        return $this->exitCode($result);
    }

    private function withOption(RunnerInterface $runner, string $name, mixed $value): RunnerInterface
    {
        $request        = $runner->request();
        $options        = $request->options();
        $options[$name] = $value;

        return new ScopedRunner(
            $runner,
            new Request(
                params: $request->params(),
                options: $options,
                extra: $request->extra(),
                errors: $request->errors(),
            ),
        );
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
