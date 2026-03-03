<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Provision\Step;

use PhpSoftBox\Config\Config;
use PhpSoftBox\MultiTenant\Contracts\TenantProvisionCommandRunnerInterface;
use PhpSoftBox\MultiTenant\Contracts\TenantProvisionStepInterface;
use PhpSoftBox\MultiTenant\Provision\TenantProvisionContext;
use RuntimeException;

use function array_keys;
use function array_values;
use function is_array;
use function is_string;
use function str_contains;
use function str_replace;
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

        foreach ($commands as $command) {
            $resolved = $this->interpolate($command, $context);
            if (str_contains($resolved, '{') && str_contains($resolved, '}')) {
                throw new RuntimeException(
                    'В команде provisioning остались неразрешенные placeholders: ' . $resolved,
                );
            }

            $this->runner->run($resolved);
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

    private function interpolate(string $command, TenantProvisionContext $context): string
    {
        $payload      = $context->payload();
        $replacements = [
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

        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $command,
        );
    }
}
