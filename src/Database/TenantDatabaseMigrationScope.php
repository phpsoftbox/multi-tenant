<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Database;

use PhpSoftBox\MultiTenant\Context\TenantContext;
use PhpSoftBox\MultiTenant\Contracts\TenantConnectionSwitcherInterface;
use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;

final readonly class TenantDatabaseMigrationScope
{
    public function __construct(
        private TenantDsnResolver $dsnResolver,
        private TenantConnectionSwitcherInterface $connectionSwitcher,
    ) {
    }

    /**
     * @template T
     * @param callable(string): T $callback
     * @return T
     */
    public function run(TenantDefinition $tenant, callable $callback): mixed
    {
        $dsn = $this->dsnResolver->resolve(new TenantContext($tenant));

        $this->connectionSwitcher->activate($dsn);
        try {
            return $callback($tenant->databaseConnection);
        } finally {
            $this->connectionSwitcher->deactivate();
        }
    }
}
