<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tenant\Runtime;

use PhpSoftBox\MultiTenant\Bootstrap\TenantBootstrapPipeline;
use PhpSoftBox\MultiTenant\Context\TenantContext;
use PhpSoftBox\MultiTenant\Context\TenantContextFactory;
use PhpSoftBox\MultiTenant\Context\TenantRuntimeScope;
use PhpSoftBox\MultiTenant\Contracts\TenantContextStoreInterface;
use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;

final readonly class TenantRuntimeExecutor
{
    public function __construct(
        private TenantContextFactory $contextFactory,
        private TenantBootstrapPipeline $pipeline,
        private TenantContextStoreInterface $contextStore,
    ) {
    }

    public function run(
        TenantDefinition $tenant,
        callable $callback,
        TenantRuntimeScope $scope = TenantRuntimeScope::Cli,
    ): mixed {
        $previous = $this->contextStore->get();
        $context  = $this->contextFactory->create($tenant, $scope);
        $this->contextStore->set($context);

        try {
            return $this->pipeline->run(
                $context,
                static fn (TenantContext $runtimeContext): mixed => $callback($runtimeContext),
                $scope,
            );
        } finally {
            if ($previous !== null) {
                $this->contextStore->set($previous);
            } else {
                $this->contextStore->clear();
            }
        }
    }
}
