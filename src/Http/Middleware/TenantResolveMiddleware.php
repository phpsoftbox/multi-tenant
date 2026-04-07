<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Http\Middleware;

use PhpSoftBox\MultiTenant\Context\TenantRuntimeScope;
use PhpSoftBox\MultiTenant\Contracts\ReloadableTenantProviderInterface;
use PhpSoftBox\MultiTenant\Contracts\TenantProviderInterface;
use PhpSoftBox\MultiTenant\Profiler\MultiTenantProfilerCollector;
use PhpSoftBox\MultiTenant\Tenant\Runtime\TenantRuntimeExecutor;
use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;
use PhpSoftBox\MultiTenant\Tenant\TenantNotFoundException;
use PhpSoftBox\Profiler\ProfilerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

use function get_class;
use function hrtime;
use function round;
use function strtolower;
use function trim;

final readonly class TenantResolveMiddleware implements MiddlewareInterface
{
    public const string DEFAULT_TENANT_ATTRIBUTE = '_tenant';

    public function __construct(
        private TenantProviderInterface $tenants,
        private TenantRuntimeExecutor $tenantRuntime,
        private string $tenantAttribute = self::DEFAULT_TENANT_ATTRIBUTE,
        private ?ProfilerInterface $profiler = null,
        private ?MultiTenantProfilerCollector $profilerCollector = null,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $start = hrtime(true);
        $host  = strtolower(trim($request->getUri()->getHost()));
        $tags  = ['host' => $host];
        $span  = $this->profiler?->currentTrace() !== null
            ? $this->profiler->start('tenant.resolve', $tags, 'multi_tenant')
            : null;

        try {
            if ($this->tenants instanceof ReloadableTenantProviderInterface) {
                $this->profileProviderReload($this->tenants, $tags);
            }

            $tenant = $this->profileFindByHost($host, $tags);
            if (!$tenant instanceof TenantDefinition) {
                throw TenantNotFoundException::forHost($host);
            }

            $request = $request->withAttribute($this->tenantAttribute, $tenant);

            $this->recordProfilerEvent('tenant.resolve', $tags + [
                'tenant_id'   => $tenant->id,
                'tenant_name' => $tenant->name,
                'found'       => true,
            ], $start);

            return $this->tenantRuntime->run(
                tenant: $tenant,
                callback: static fn (mixed $_context): ResponseInterface => $handler->handle($request),
                scope: TenantRuntimeScope::Http,
            );
        } catch (Throwable $exception) {
            $span?->fail($exception);
            $this->recordProfilerEvent(
                'tenant.resolve',
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
    private function profileProviderReload(ReloadableTenantProviderInterface $tenants, array $tags): void
    {
        $start = hrtime(true);
        $span  = $this->profiler?->currentTrace() !== null
            ? $this->profiler->start('tenant.provider.reload', $tags, 'multi_tenant')
            : null;

        try {
            $tenants->reload();
            $this->recordProfilerEvent('tenant.provider.reload', $tags, $start);
        } catch (Throwable $exception) {
            $span?->fail($exception);
            $this->recordProfilerEvent(
                'tenant.provider.reload',
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

    private function profileFindByHost(string $host, array $tags): ?TenantDefinition
    {
        if ($host === '') {
            throw TenantNotFoundException::forEmptyHost();
        }

        $start = hrtime(true);
        $span  = $this->profiler?->currentTrace() !== null
            ? $this->profiler->start('tenant.provider.find_by_host', $tags, 'multi_tenant')
            : null;

        try {
            $tenant = $this->tenants->findByHost($host);
            $this->recordProfilerEvent('tenant.provider.find_by_host', $tags + [
                'found'       => $tenant instanceof TenantDefinition,
                'tenant_id'   => $tenant?->id,
                'tenant_name' => $tenant?->name,
            ], $start);

            return $tenant;
        } catch (Throwable $exception) {
            $span?->fail($exception);
            $this->recordProfilerEvent(
                'tenant.provider.find_by_host',
                $tags,
                $start,
                failed: true,
                exceptionClass: get_class($exception),
            );

            throw $exception;
        }
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
