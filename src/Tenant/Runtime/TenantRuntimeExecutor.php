<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tenant\Runtime;

use PhpSoftBox\MultiTenant\Bootstrap\TenantBootstrapPipeline;
use PhpSoftBox\MultiTenant\Context\TenantContext;
use PhpSoftBox\MultiTenant\Context\TenantContextFactory;
use PhpSoftBox\MultiTenant\Context\TenantRuntimeScope;
use PhpSoftBox\MultiTenant\Contracts\TenantContextStoreInterface;
use PhpSoftBox\MultiTenant\Profiler\MultiTenantProfilerCollector;
use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;
use PhpSoftBox\Profiler\ProfilerInterface;
use Throwable;

use function get_class;
use function hrtime;
use function round;

final readonly class TenantRuntimeExecutor
{
    public function __construct(
        private TenantContextFactory $contextFactory,
        private TenantBootstrapPipeline $pipeline,
        private TenantContextStoreInterface $contextStore,
        private ?ProfilerInterface $profiler = null,
        private ?MultiTenantProfilerCollector $profilerCollector = null,
    ) {
    }

    public function run(
        TenantDefinition $tenant,
        callable $callback,
        TenantRuntimeScope $scope = TenantRuntimeScope::Cli,
    ): mixed {
        $start = hrtime(true);
        $tags  = $this->tenantTags($tenant, $scope);
        $span  = $this->profiler?->currentTrace() !== null
            ? $this->profiler->start('tenant.runtime', $tags, 'multi_tenant')
            : null;

        try {
            $previous = $this->contextStore->get();
            $context  = $this->contextFactory->create($tenant, $scope);
            $this->contextStore->set($context);

            try {
                $result = $this->pipeline->run(
                    $context,
                    static fn (TenantContext $runtimeContext): mixed => $callback($runtimeContext),
                    $scope,
                );

                $this->recordProfilerEvent('tenant.runtime', $tags, $start);

                return $result;
            } finally {
                if ($previous !== null) {
                    $this->contextStore->set($previous);
                } else {
                    $this->contextStore->clear();
                }
            }
        } catch (Throwable $exception) {
            $span?->fail($exception);
            $this->recordProfilerEvent(
                'tenant.runtime',
                $tags,
                $start,
                failed: true,
                exceptionClass: get_class($exception),
            );

            throw $exception;
        } finally {
            $span?->finish();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function tenantTags(TenantDefinition $tenant, TenantRuntimeScope $scope): array
    {
        return [
            'tenant_id'   => $tenant->id,
            'tenant_name' => $tenant->name,
            'host'        => $tenant->host,
            'scope'       => $scope->value,
        ];
    }

    /**
     * @param array<string, mixed> $tags
     */
    private function recordProfilerEvent(
        string $event,
        array $tags,
        int $startedAtNs,
        bool $failed = false,
        ?string $exceptionClass = null,
    ): void {
        $this->profilerCollector?->recordEvent(
            event: $event,
            tags: $tags,
            durationMs: round((hrtime(true) - $startedAtNs) / 1_000_000, 3),
            failed: $failed,
            exceptionClass: $exceptionClass,
        );
    }
}
