<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Bootstrap;

use PhpSoftBox\MultiTenant\Context\TenantContext;
use PhpSoftBox\MultiTenant\Context\TenantRuntimeScope;
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
        private readonly TenantRuntimeScope $scope = TenantRuntimeScope::Cli,
        private readonly ?TenantBootstrapPipeline $pipeline = null,
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
            if ($this->pipeline !== null) {
                $this->pipeline->teardownBootstrapper($this->context, $bootstrapper, $this->scope);

                continue;
            }

            $bootstrapper->teardown($this->context);
        }
    }
}
