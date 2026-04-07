<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests\Support;

use PhpSoftBox\MultiTenant\Contracts\TenantConnectionSwitcherInterface;

use function array_pop;

final class StackTenantConnectionSwitcher implements TenantConnectionSwitcherInterface
{
    /** @var list<?string> */
    private array $stack = [];

    private ?string $activeDsn = null;

    /** @var list<string> */
    public array $calls = [];

    public function activate(string $dsn): void
    {
        $this->stack[]   = $this->activeDsn;
        $this->activeDsn = $dsn;
        $this->calls[]   = 'activate:' . $dsn;
    }

    public function deactivate(): void
    {
        $this->activeDsn = array_pop($this->stack);
        $this->calls[]   = 'deactivate:' . ($this->activeDsn ?? 'none');
    }

    public function activeDsn(): ?string
    {
        return $this->activeDsn;
    }
}
