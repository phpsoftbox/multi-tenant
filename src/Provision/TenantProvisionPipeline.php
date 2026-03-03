<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Provision;

use PhpSoftBox\MultiTenant\Contracts\TenantProvisionStepInterface;

use function usort;

final class TenantProvisionPipeline
{
    /** @var list<TenantProvisionStepInterface> */
    private array $steps;

    /**
     * @param iterable<TenantProvisionStepInterface> $steps
     */
    public function __construct(iterable $steps = [])
    {
        $normalized = [];
        foreach ($steps as $step) {
            $normalized[] = $step;
        }

        usort(
            $normalized,
            static fn (TenantProvisionStepInterface $left, TenantProvisionStepInterface $right): int => $right->priority() <=> $left->priority(),
        );

        $this->steps = $normalized;
    }

    public function run(TenantProvisionContext $context): TenantProvisionContext
    {
        foreach ($this->steps as $step) {
            $step->run($context);
        }

        return $context;
    }
}
