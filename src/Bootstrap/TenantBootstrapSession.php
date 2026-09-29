<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Bootstrap;

use PhpSoftBox\MultiTenant\Context\TenantContext;
use PhpSoftBox\MultiTenant\Context\TenantRuntimeScope;
use PhpSoftBox\MultiTenant\Contracts\TenantBootstrapperInterface;
use Throwable;

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
        $first        = null;

        // Ошибка teardown одного bootstrapper-а не должна оставлять остальные в состоянии арендатора:
        // откатываем все, затем бросаем первое исключение.
        foreach (array_reverse($this->bootstrappers) as $bootstrapper) {
            try {
                if ($this->pipeline !== null) {
                    $this->pipeline->teardownBootstrapper($this->context, $bootstrapper, $this->scope);
                } else {
                    $bootstrapper->teardown($this->context);
                }
            } catch (Throwable $exception) {
                $first ??= $exception;
            }
        }

        if ($first !== null) {
            throw $first;
        }
    }
}
