<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Contracts;

interface TenantConnectionSwitcherInterface
{
    public function activate(string $dsn): void;

    public function deactivate(): void;

    public function activeDsn(): ?string;
}
