<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests;

use PhpSoftBox\Http\Message\ResponseFactory;
use PhpSoftBox\Http\Message\ServerRequest;
use PhpSoftBox\MultiTenant\Bootstrap\TenantBootstrapPipeline;
use PhpSoftBox\MultiTenant\Context\InMemoryTenantContextStore;
use PhpSoftBox\MultiTenant\Context\TenantContextFactory;
use PhpSoftBox\MultiTenant\Contracts\TenantProviderInterface;
use PhpSoftBox\MultiTenant\Http\Middleware\TenantResolveMiddleware;
use PhpSoftBox\MultiTenant\Tenant\Runtime\TenantRuntimeExecutor;
use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;
use PhpSoftBox\MultiTenant\Tenant\TenantNotFoundException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function array_filter;
use function array_values;

#[CoversClass(TenantResolveMiddleware::class)]
#[CoversClass(TenantNotFoundException::class)]
final class TenantResolveMiddlewareTest extends TestCase
{
    /**
     * Проверяем, что при пустом host middleware выбрасывает TenantNotFoundException.
     */
    #[Test]
    public function throwsWhenHostIsEmpty(): void
    {
        $middleware = new TenantResolveMiddleware(
            tenants: $this->provider([]),
            tenantRuntime: $this->runtime(),
        );

        $request = new ServerRequest('GET', '/');

        $handler = new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new ResponseFactory()->createResponse(200);
            }
        };

        $this->expectException(TenantNotFoundException::class);
        $this->expectExceptionMessage('Tenant host is empty.');

        $middleware->process($request, $handler);
    }

    /**
     * Проверяем, что при неизвестном host middleware выбрасывает TenantNotFoundException.
     */
    #[Test]
    public function throwsWhenTenantIsNotFoundByHost(): void
    {
        $middleware = new TenantResolveMiddleware(
            tenants: $this->provider([]),
            tenantRuntime: $this->runtime(),
        );

        $request = new ServerRequest('GET', 'https://unknown.example.test');

        $handler = new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new ResponseFactory()->createResponse(200);
            }
        };

        $this->expectException(TenantNotFoundException::class);
        $this->expectExceptionMessage('Tenant is not resolved for host: unknown.example.test');

        $middleware->process($request, $handler);
    }

    /**
     * Проверяем, что middleware резолвит tenant, прокидывает его в request-атрибут и запускает runtime.
     */
    #[Test]
    public function resolvesTenantAndDelegatesToHandler(): void
    {
        $tenant = new TenantDefinition(
            id: '1',
            name: 'Tenant 1',
            host: 'tenant1.example.test',
            databaseConnection: 'tenant',
            enabled: true,
            primaryDomain: 'tenant1.example.test',
            domains: ['tenant1.example.test'],
        );

        $middleware = new TenantResolveMiddleware(
            tenants: $this->provider([$tenant]),
            tenantRuntime: $this->runtime(),
        );

        $request = new ServerRequest('GET', 'https://tenant1.example.test/dashboard');

        $state = new class () {
            public ?TenantDefinition $seenTenant = null;
        };
        $handler = new class ($state) implements RequestHandlerInterface {
            public function __construct(
                private object $state,
            ) {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $tenant                  = $request->getAttribute(TenantResolveMiddleware::DEFAULT_TENANT_ATTRIBUTE);
                $this->state->seenTenant = $tenant instanceof TenantDefinition ? $tenant : null;

                return new ResponseFactory()->createResponse(204);
            }
        };

        $response = $middleware->process($request, $handler);

        $this->assertSame(204, $response->getStatusCode());
        $this->assertInstanceOf(TenantDefinition::class, $state->seenTenant);
        $this->assertSame('1', $state->seenTenant?->id);
    }

    /**
     * @param list<TenantDefinition> $tenants
     */
    private function provider(array $tenants): TenantProviderInterface
    {
        return new class ($tenants) implements TenantProviderInterface {
            /**
             * @param list<TenantDefinition> $tenants
             */
            public function __construct(
                private readonly array $tenants,
            ) {
            }

            public function all(bool $onlyEnabled = true): array
            {
                if (!$onlyEnabled) {
                    return $this->tenants;
                }

                return array_values(array_filter(
                    $this->tenants,
                    static fn (TenantDefinition $tenant): bool => $tenant->enabled,
                ));
            }

            public function findById(string $id): ?TenantDefinition
            {
                foreach ($this->tenants as $tenant) {
                    if ($tenant->id === $id) {
                        return $tenant;
                    }
                }

                return null;
            }

            public function findByHost(string $host): ?TenantDefinition
            {
                foreach ($this->tenants as $tenant) {
                    if ($tenant->host === $host) {
                        return $tenant;
                    }
                }

                return null;
            }
        };
    }

    private function runtime(): TenantRuntimeExecutor
    {
        return new TenantRuntimeExecutor(
            contextFactory: new TenantContextFactory(),
            pipeline: new TenantBootstrapPipeline(),
            contextStore: new InMemoryTenantContextStore(),
        );
    }
}
