<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Bootstrap;

use PhpSoftBox\MultiTenant\Context\TenantContext;
use PhpSoftBox\MultiTenant\Context\TenantRuntimeScope;
use PhpSoftBox\MultiTenant\Contracts\TenantBootstrapperInterface;
use Throwable;

use function array_reverse;
use function usort;

final class TenantBootstrapPipeline
{
    /** @var list<TenantBootstrapperInterface> */
    private array $bootstrappers;

    /**
     * @param iterable<TenantBootstrapperInterface> $bootstrappers
     */
    public function __construct(iterable $bootstrappers = [])
    {
        $normalized = [];
        foreach ($bootstrappers as $bootstrapper) {
            $normalized[] = $bootstrapper;
        }

        usort(
            $normalized,
            static fn (TenantBootstrapperInterface $left, TenantBootstrapperInterface $right): int => $right->priority() <=> $left->priority(),
        );

        $this->bootstrappers = $normalized;
    }

    public function begin(
        TenantContext $context,
        TenantRuntimeScope $scope = TenantRuntimeScope::Cli,
    ): TenantBootstrapSession {
        $executed = [];

        try {
            foreach ($this->bootstrappers as $bootstrapper) {
                if (!$bootstrapper->supports($scope)) {
                    continue;
                }

                $bootstrapper->bootstrap($context);
                $executed[] = $bootstrapper;
            }
        } catch (Throwable $exception) {
            foreach (array_reverse($executed) as $applied) {
                $applied->teardown($context);
            }

            throw $exception;
        }

        return new TenantBootstrapSession($context, $executed);
    }

    public function run(
        TenantContext $context,
        callable $callback,
        TenantRuntimeScope $scope = TenantRuntimeScope::Cli,
    ): mixed {
        $session = $this->begin($context, $scope);

        try {
            return $callback($session->context());
        } finally {
            $session->teardown();
        }
    }
}
