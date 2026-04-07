<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Bootstrap;

use PhpSoftBox\MultiTenant\Context\TenantContext;
use PhpSoftBox\MultiTenant\Context\TenantRuntimeScope;
use PhpSoftBox\MultiTenant\Contracts\TenantBootstrapperInterface;
use PhpSoftBox\MultiTenant\Profiler\MultiTenantProfilerCollector;
use PhpSoftBox\Profiler\ProfilerInterface;
use Throwable;

use function array_reverse;
use function get_class;
use function hrtime;
use function round;
use function usort;

final class TenantBootstrapPipeline
{
    /** @var list<TenantBootstrapperInterface> */
    private array $bootstrappers;

    /**
     * @param iterable<TenantBootstrapperInterface> $bootstrappers
     */
    public function __construct(
        iterable $bootstrappers = [],
        private readonly ?ProfilerInterface $profiler = null,
        private readonly ?MultiTenantProfilerCollector $profilerCollector = null,
    ) {
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
                    $this->recordProfilerEvent('tenant.bootstrap.skip', $this->bootstrapperTags($context, $bootstrapper, $scope));

                    continue;
                }

                $this->profileBootstrapper(
                    'tenant.bootstrap',
                    static fn (): mixed => $bootstrapper->bootstrap($context),
                    $context,
                    $bootstrapper,
                    $scope,
                );
                $executed[] = $bootstrapper;
            }
        } catch (Throwable $exception) {
            foreach (array_reverse($executed) as $applied) {
                $this->profileBootstrapper(
                    'tenant.bootstrap.rollback',
                    static fn (): mixed => $applied->teardown($context),
                    $context,
                    $applied,
                    $scope,
                );
            }

            throw $exception;
        }

        return new TenantBootstrapSession($context, $executed, $scope, $this);
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

    public function teardownBootstrapper(
        TenantContext $context,
        TenantBootstrapperInterface $bootstrapper,
        TenantRuntimeScope $scope,
    ): void {
        $this->profileBootstrapper(
            'tenant.bootstrap.teardown',
            static fn (): mixed => $bootstrapper->teardown($context),
            $context,
            $bootstrapper,
            $scope,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function bootstrapperTags(
        TenantContext $context,
        TenantBootstrapperInterface $bootstrapper,
        TenantRuntimeScope $scope,
    ): array {
        $tenant = $context->tenant();

        return [
            'tenant_id'    => $tenant->id,
            'tenant_name'  => $tenant->name,
            'scope'        => $scope->value,
            'bootstrapper' => get_class($bootstrapper),
        ];
    }

    private function profileBootstrapper(
        string $event,
        callable $callback,
        TenantContext $context,
        TenantBootstrapperInterface $bootstrapper,
        TenantRuntimeScope $scope,
    ): mixed {
        $start = hrtime(true);
        $tags  = $this->bootstrapperTags($context, $bootstrapper, $scope);
        $span  = $this->profiler?->currentTrace() !== null
            ? $this->profiler->start($event, $tags, 'multi_tenant')
            : null;

        try {
            $result = $callback();
            $this->recordProfilerEvent($event, $tags, $start);

            return $result;
        } catch (Throwable $exception) {
            $span?->fail($exception);
            $this->recordProfilerEvent(
                $event,
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
     * @param array<string, mixed> $tags
     */
    private function recordProfilerEvent(
        string $event,
        array $tags,
        ?int $startedAtNs = null,
        bool $failed = false,
        ?string $exceptionClass = null,
    ): void {
        $durationMs = $startedAtNs !== null ? round((hrtime(true) - $startedAtNs) / 1_000_000, 3) : null;

        $this->profilerCollector?->recordEvent(
            event: $event,
            tags: $tags,
            durationMs: $durationMs,
            failed: $failed,
            exceptionClass: $exceptionClass,
        );
    }
}
