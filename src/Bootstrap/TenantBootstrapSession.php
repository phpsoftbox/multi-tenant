<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Bootstrap;

use PhpSoftBox\MultiTenant\Context\TenantContext;
use PhpSoftBox\MultiTenant\Contracts\TenantBootstrapperInterface;

use function array_reverse;

final class TenantBootstrapSession
{
    private bool $closed = false;

    /**
     * @param list<TenantBootstrapperInterface> $bootstrappers
     */
    public function __construct(
        private readonly TenantContext $context,
        private readonly array $bootstrappers,
    ) {
    }

    public function context(): TenantContext
    {
        return $this->context;
    }

    public function teardown(): void
    {
        if ($this->closed) {
            return;
        }

        $this->closed = true;

        foreach (array_reverse($this->bootstrappers) as $bootstrapper) {
            $bootstrapper->teardown($this->context);
        }
    }
}
