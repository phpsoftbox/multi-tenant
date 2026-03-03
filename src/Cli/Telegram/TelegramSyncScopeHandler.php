<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Cli\Telegram;

use PhpSoftBox\CliApp\Command\HandlerInterface;
use PhpSoftBox\CliApp\Response;
use PhpSoftBox\CliApp\Runner\RunnerInterface;
use PhpSoftBox\Config\Config;
use PhpSoftBox\MultiTenant\Telegram\TenantTelegramBotRegistryFactory;
use PhpSoftBox\MultiTenant\Tenant\Runtime\TenantRuntimeExecutor;
use PhpSoftBox\MultiTenant\Tenant\TenantSelector;
use PhpSoftBox\Telegram\Bot\TelegramBotRegistry;
use Throwable;

use function implode;
use function in_array;
use function is_string;
use function rtrim;
use function str_contains;
use function str_replace;
use function trim;

final readonly class TelegramSyncScopeHandler implements HandlerInterface
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

        $botName = $runner->request()->option('bot', '');
        if (!is_string($botName)) {
            $runner->io()->writeln('Параметр --bot должен быть строкой.', 'error');

            return Response::FAILURE;
        }

        $botName       = trim($botName);
        $updateWebhook = $runner->request()->option('webhook', false) === true;

        $errors = 0;

        if ($scope === 'core' || $scope === 'all') {
            $runner->io()->writeln('[core] telegram:sync start', 'info');

            $coreBaseUrl = trim((string) $this->config->get('app.admin_url', ''));
            $result      = $this->syncRegistry(
                runner: $runner,
                registry: $this->coreRegistry,
                botName: $botName,
                updateWebhook: $updateWebhook,
                fallbackBaseUrl: $coreBaseUrl,
                scopeLabel: 'core',
            );

            if ($result !== Response::SUCCESS) {
                $errors++;
            }
        }

        if ($scope === 'tenant' || $scope === 'all') {
            $tenantResult = $this->runForTenants(
                runner: $runner,
                tenantOption: $tenantOption,
                botName: $botName,
                updateWebhook: $updateWebhook,
            );

            if ($tenantResult !== Response::SUCCESS) {
                $errors++;
            }
        }

        return $errors === 0 ? Response::SUCCESS : Response::FAILURE;
    }

    private function runForTenants(RunnerInterface $runner, string $tenantOption, string $botName, bool $updateWebhook): int
    {
        try {
            $tenants = $this->selector->select($tenantOption, true);
        } catch (Throwable $exception) {
            $runner->io()->writeln($exception->getMessage(), 'error');

            return Response::FAILURE;
        }

        if ($tenants === []) {
            $runner->io()->writeln('Не найдено tenant для telegram:sync.', 'warning');

            return Response::SUCCESS;
        }

        $errors = 0;

        foreach ($tenants as $tenant) {
            $runner->io()->writeln('[tenant:' . $tenant->id . '] telegram:sync start', 'info');

            try {
                $result = $this->runtime->run($tenant, function () use ($runner, $tenant, $botName, $updateWebhook) {
                    $registry = $this->tenantRegistryFactory->create($tenant);
                    if ($registry->names() === []) {
                        $runner->io()->writeln('[tenant:' . $tenant->id . '] не настроены telegram bots.', 'warning');

                        return Response::SUCCESS;
                    }

                    $baseUrl = $tenant->primaryDomain !== null && trim($tenant->primaryDomain) !== ''
                        ? 'https://' . trim($tenant->primaryDomain)
                        : '';

                    return $this->syncRegistry(
                        runner: $runner,
                        registry: $registry,
                        botName: $botName,
                        updateWebhook: $updateWebhook,
                        fallbackBaseUrl: $baseUrl,
                        scopeLabel: 'tenant:' . $tenant->id,
                    );
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

    private function syncRegistry(
        RunnerInterface $runner,
        TelegramBotRegistry $registry,
        string $botName,
        bool $updateWebhook,
        string $fallbackBaseUrl,
        string $scopeLabel,
    ): int {
        $resolvedName = $this->resolveBotName($registry, $botName);
        $bot          = $resolvedName !== '' ? $registry->bot($resolvedName) : null;
        if ($bot === null) {
            $available = $registry->names();
            $runner->io()->writeln(
                '[' . $scopeLabel . '] Бот "' . ($resolvedName !== '' ? $resolvedName : 'default') . '" не найден. Доступные: ' . ($available !== [] ? implode(', ', $available) : 'нет'),
                'error',
            );

            return Response::FAILURE;
        }

        $commands = $this->commandsFor($resolvedName);
        if ($commands === []) {
            $runner->io()->writeln('[' . $scopeLabel . '] Нет команд для бота: ' . $resolvedName, 'error');

            return Response::FAILURE;
        }

        try {
            $bot->client()->setMyCommands($commands);
        } catch (Throwable $exception) {
            $runner->io()->writeln('[' . $scopeLabel . '] Ошибка Telegram: ' . $exception->getMessage(), 'error');

            return Response::FAILURE;
        }

        $runner->io()->writeln('[' . $scopeLabel . '] Команды обновлены для бота: ' . $resolvedName, 'success');

        if (!$updateWebhook) {
            return Response::SUCCESS;
        }

        $webhookUrl = $this->resolveWebhookUrl($runner, $resolvedName, $fallbackBaseUrl);
        if ($webhookUrl === '') {
            $runner->io()->writeln('[' . $scopeLabel . '] Не удалось определить URL webhook.', 'error');

            return Response::FAILURE;
        }

        try {
            $bot->client()->setWebhook($webhookUrl);
        } catch (Throwable $exception) {
            $runner->io()->writeln('[' . $scopeLabel . '] Ошибка Telegram: ' . $exception->getMessage(), 'error');

            return Response::FAILURE;
        }

        $runner->io()->writeln('[' . $scopeLabel . '] Webhook обновлён: ' . $webhookUrl, 'success');

        return Response::SUCCESS;
    }

    private function resolveWebhookUrl(RunnerInterface $runner, string $botName, string $fallbackBaseUrl): string
    {
        $baseUrl = (string) $runner->request()->option('base-url', '');
        $path    = (string) $runner->request()->option('path', '');

        $baseUrl = trim($baseUrl);
        if ($baseUrl === '') {
            $baseUrl = trim($fallbackBaseUrl);
        }

        $baseUrl = rtrim($baseUrl, '/');
        if ($baseUrl === '') {
            return '';
        }

        $path = trim($path);
        if ($path === '') {
            $path = '/telegram/{bot}/webhook';
        }

        if (!str_contains($path, '{bot}')) {
            $path = rtrim($path, '/') . '/{bot}/webhook';
        }

        $path = str_replace('{bot}', $botName, $path);

        return $baseUrl . $path;
    }

    /**
     * @return array<int, array{command:string,description:string}>
     */
    private function commandsFor(string $botName): array
    {
        if ($botName === 'main_tenant') {
            return [
                ['command' => 'start', 'description' => 'Показать меню'],
                ['command' => 'confirm', 'description' => 'Подтверждение номера'],
                ['command' => 'help', 'description' => 'Справка'],
            ];
        }

        if (in_array($botName, ['account', 'main'], true)) {
            return [
                ['command' => 'start', 'description' => 'Показать меню'],
                ['command' => 'confirm', 'description' => 'Подтверждение номера'],
                ['command' => 'reset', 'description' => 'Сброс пароля'],
                ['command' => 'help', 'description' => 'Справка'],
            ];
        }

        return [];
    }

    private function resolveBotName(TelegramBotRegistry $registry, string $requestedBotName): string
    {
        $requestedBotName = trim($requestedBotName);
        if ($requestedBotName !== '') {
            return $requestedBotName;
        }

        $default = trim($registry->defaultName());
        if ($default !== '' && $registry->has($default)) {
            return $default;
        }

        if ($registry->has('account')) {
            return 'account';
        }

        $available = $registry->names();

        return $available[0] ?? '';
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
