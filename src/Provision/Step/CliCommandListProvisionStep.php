<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Provision\Step;

use PhpSoftBox\Config\Config;
use PhpSoftBox\MultiTenant\Contracts\TenantProvisionCommandRunnerInterface;
use PhpSoftBox\MultiTenant\Contracts\TenantProvisionStepInterface;
use PhpSoftBox\MultiTenant\Provision\Runner\CommandLineSplitter;
use PhpSoftBox\MultiTenant\Provision\TenantProvisionContext;
use RuntimeException;

use function array_key_exists;
use function array_values;
use function is_array;
use function is_string;
use function preg_match_all;
use function strtr;
use function trim;

final readonly class CliCommandListProvisionStep implements TenantProvisionStepInterface
{
    public function __construct(
        private TenantProvisionCommandRunnerInterface $runner,
        private Config $config,
    ) {
    }

    public function id(): string
    {
        return 'commands.cli';
    }

    public function priority(): int
    {
        return 100;
    }

    public function run(TenantProvisionContext $context): void
    {
        $commands = $this->resolveCommands($context);
        if ($commands === []) {
            return;
        }

        $replacements = $this->replacements($context);

        foreach ($commands as $command) {
            // Шаблон разбивается на аргументы до подстановки: значения из payload становятся целыми аргументами и
            // не разбираются как часть командной строки.
            $arguments = [];
            foreach (CommandLineSplitter::split($command) as $argument) {
                if (preg_match_all('/\{[a-z_]+\}/', $argument, $matches) > 0) {
                    foreach ($matches[0] as $placeholder) {
                        if (!array_key_exists($placeholder, $replacements)) {
                            throw new RuntimeException(
                                'В команде provisioning неизвестный placeholder ' . $placeholder . ': ' . $command,
                            );
                        }
                    }

                    $argument = strtr($argument, $replacements);
                }

                $arguments[] = $argument;
            }

            $this->runner->run($arguments);
        }
    }

    /**
     * @return list<string>
     */
    private function resolveCommands(TenantProvisionContext $context): array
    {
        $commands = $context->payload()->extra['commands'] ?? $this->config->get('tenancy.provision.commands', []);
        if (!is_array($commands)) {
            return [];
        }

        $result = [];
        foreach (array_values($commands) as $command) {
            if (!is_string($command) || trim($command) === '') {
                continue;
            }

            $result[] = trim($command);
        }

        return $result;
    }

    /**
     * @return array<string, string>
     */
    private function replacements(TenantProvisionContext $context): array
    {
        $payload = $context->payload();

        return [
            '{tenant_id}'           => $context->tenant()->id,
            '{tenant_connection}'   => $context->tenant()->databaseConnection,
            '{tenant_database}'     => $context->tenant()->databaseName ?? '',
            '{template_tenant_id}'  => $context->templateTenant()->id,
            '{template_connection}' => $context->templateTenant()->databaseConnection,
            '{owner_phone}'         => $payload->ownerPhone ?? '',
            '{owner_name}'          => $payload->ownerName ?? '',
            '{owner_email}'         => $payload->ownerEmail ?? '',
            '{confirm_owner_phone}' => $payload->confirmOwnerPhone ? '1' : '0',
        ];
    }
}
