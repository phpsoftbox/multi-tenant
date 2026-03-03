<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Provision\Step;

use PhpSoftBox\Config\Config;
use PhpSoftBox\MultiTenant\Contracts\TenantProvisionStepInterface;
use PhpSoftBox\MultiTenant\Database\TenantDatabaseProvisionService;
use PhpSoftBox\MultiTenant\Provision\TenantProvisionContext;

use function is_array;
use function is_string;
use function trim;

final readonly class DatabaseCloneProvisionStep implements TenantProvisionStepInterface
{
    public function __construct(
        private TenantDatabaseProvisionService $provision,
        private Config $config,
    ) {
    }

    public function id(): string
    {
        return 'database.clone';
    }

    public function priority(): int
    {
        return 300;
    }

    public function run(TenantProvisionContext $context): void
    {
        $databaseOptions = $this->config->get('tenancy.provision.database', []);
        $payloadDatabase = $context->payload()->extra['database'] ?? [];

        if (!is_array($databaseOptions)) {
            $databaseOptions = [];
        }

        if (!is_array($payloadDatabase)) {
            $payloadDatabase = [];
        }

        $migrationsTable = $payloadDatabase['migrations_table'] ?? ($databaseOptions['migrations_table'] ?? 'migrations');
        if (!is_string($migrationsTable) || trim($migrationsTable) === '') {
            $migrationsTable = 'migrations';
        }

        $dropExisting = (bool) ($payloadDatabase['drop_existing'] ?? ($databaseOptions['drop_existing'] ?? false));

        $this->provision->provision(
            templateConnection: $context->templateTenant()->databaseConnection,
            targetConnection: $context->tenant()->databaseConnection,
            migrationsTable: trim($migrationsTable),
            dropExisting: $dropExisting,
        );
    }
}
