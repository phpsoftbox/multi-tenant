<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests;

use PhpSoftBox\Http\Message\ResponseFactory;
use PhpSoftBox\Http\Message\ServerRequest;
use PhpSoftBox\MultiTenant\Bootstrap\TenantBootstrapPipeline;
use PhpSoftBox\MultiTenant\Context\InMemoryTenantContextStore;
use PhpSoftBox\MultiTenant\Context\TenantContextFactory;
use PhpSoftBox\MultiTenant\Contracts\ReloadableTenantProviderInterface;
use PhpSoftBox\MultiTenant\Contracts\TenantProviderInterface;
use PhpSoftBox\MultiTenant\Contracts\TenantRequestResolverInterface;
use PhpSoftBox\MultiTenant\Http\Middleware\TenantResolveMiddleware;
use PhpSoftBox\MultiTenant\Http\Resolver\HeaderTenantRequestResolver;
use PhpSoftBox\MultiTenant\Tenant\Runtime\TenantRuntimeExecutor;
use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;
use PhpSoftBox\MultiTenant\Tenant\TenantNotFoundException;
use PhpSoftBox\MultiTenant\Tests\Support\TestTenantProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

#[CoversClass(TenantResolveMiddleware::class)]
#[CoversMethod(TenantResolveMiddleware::class, 'process')]
final class TenantHeaderMiddlewareTest extends TestCase
{
    /**
     * Проверяет tenant из заголовка в атрибуте запроса и runtime-контексте с очисткой после ответа.
     *
     * @see TenantResolveMiddleware::process()
     */
    #[Test]
    public function headerActivatesRuntimeAndRequestAttribute(): void
    {
        $store  = new InMemoryTenantContextStore();
        $tenant = new TenantDefinition('42', 'Workspace', null, 'tenant');

        $middleware = $this->middleware($store, [$tenant]);
        $response   = new ResponseFactory()->createResponse(204);
        $handler    = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with(self::callback(
            static fn (ServerRequestInterface $request): bool => $request->getAttribute('_tenant') === $tenant
                && $store->get()?->tenant() === $tenant,
        ))->willReturn($response);

        self::assertSame($response, $middleware->process(new ServerRequest('GET', '/')->withHeader('X-Tenant-Id', '42'), $handler));
        self::assertFalse($store->has());
    }

    /**
     * Проверяет очистку tenant-контекста и передачу исключения обработчика вызывающему коду.
     *
     * @see TenantResolveMiddleware::process()
     */
    #[Test]
    public function handlerFailureClearsContext(): void
    {
        $store = new InMemoryTenantContextStore();

        $middleware = $this->middleware($store, [new TenantDefinition('42', 'Workspace', null, 'tenant')]);
        $handler    = $this->createMock(RequestHandlerInterface::class);
        $failure    = new RuntimeException('Handler failed');

        $handler->method('handle')->willThrowException($failure);

        try {
            $middleware->process(new ServerRequest('GET', '/')->withHeader('X-Tenant-Id', '42'), $handler);
            self::fail('Expected handler failure.');
        } catch (RuntimeException $exception) {
            self::assertSame($failure, $exception);
        }
        self::assertFalse($store->has());
    }

    /**
     * Проверяет, что обязательный header-only режим не запускает обработчик без tenant-заголовка.
     *
     * @see TenantResolveMiddleware::process()
     */
    #[Test]
    public function missingHeaderDoesNotStartRuntime(): void
    {
        $store   = new InMemoryTenantContextStore();
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');

        try {
            $this->middleware($store)->process(new ServerRequest('GET', 'https://api.example.test'), $handler);
            self::fail('Expected unresolved tenant.');
        } catch (TenantNotFoundException $exception) {
            self::assertNull($exception->host());
        }
        self::assertFalse($store->has());
    }

    /**
     * Проверяет возможность внедрить собственный resolver, не меняя middleware и runtime.
     *
     * @see TenantResolveMiddleware::process()
     */
    #[Test]
    public function customResolverReceivesRequestAndProvider(): void
    {
        $store    = new InMemoryTenantContextStore();
        $provider = new TestTenantProvider([]);
        $tenant   = new TenantDefinition('custom', 'Custom', null, 'tenant');
        $request  = new ServerRequest('GET', '/');
        $resolver = $this->createMock(TenantRequestResolverInterface::class);
        $resolver->expects(self::once())->method('resolve')->with($request, $provider)->willReturn($tenant);
        $middleware = new TenantResolveMiddleware($provider, $this->runtime($store), resolver: $resolver);
        $handler    = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->willReturn(new ResponseFactory()->createResponse(204));

        self::assertSame(204, $middleware->process($request, $handler)->getStatusCode());
    }

    /**
     * Проверяет, что middleware обновляет provider до поиска tenant по заголовку.
     *
     * @see TenantResolveMiddleware::process()
     */
    #[Test]
    public function reloadPrecedesHeaderLookup(): void
    {
        $provider = $this->createMockForIntersectionOfInterfaces([
            TenantProviderInterface::class,
            ReloadableTenantProviderInterface::class,
        ]);
        $reloaded = false;
        $provider->expects(self::once())->method('reload')->willReturnCallback(static function () use (&$reloaded): void {
            $reloaded = true;
        });
        $tenant = new TenantDefinition('42', 'Workspace', null, 'tenant');

        $provider->expects(self::once())->method('findById')->with('42')->willReturnCallback(
            static function () use (&$reloaded, $tenant): TenantDefinition {
                self::assertTrue($reloaded);

                return $tenant;
            },
        );
        $middleware = new TenantResolveMiddleware(
            $provider,
            $this->runtime(new InMemoryTenantContextStore()),
            resolver: new HeaderTenantRequestResolver(),
        );
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->willReturn(new ResponseFactory()->createResponse(204));

        self::assertSame(204, $middleware->process(new ServerRequest('GET', '/')->withHeader('X-Tenant-Id', '42'), $handler)->getStatusCode());
    }

    /**
     * Проверяет, что без явной настройки header resolver middleware продолжает выбирать tenant только по домену.
     *
     * @see TenantResolveMiddleware::process()
     */
    #[Test]
    public function defaultResolverIgnoresTenantHeader(): void
    {
        $tenant   = new TenantDefinition('host', 'Workspace', 'workspace.example.test', 'tenant');
        $provider = $this->createMock(TenantProviderInterface::class);
        $provider->expects(self::never())->method('findById');
        $provider->expects(self::once())->method('findByHost')->with('workspace.example.test')->willReturn($tenant);
        $middleware = new TenantResolveMiddleware($provider, $this->runtime(new InMemoryTenantContextStore()));
        $handler    = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with(self::callback(
            static fn (ServerRequestInterface $request): bool => $request->getAttribute('_tenant') === $tenant,
        ))->willReturn(new ResponseFactory()->createResponse(204));

        $middleware->process(new ServerRequest('GET', 'https://workspace.example.test')->withHeader('X-Tenant-Id', 'unknown'), $handler);
    }

    /** @param list<TenantDefinition> $tenants */
    private function middleware(InMemoryTenantContextStore $store, array $tenants = []): TenantResolveMiddleware
    {
        return new TenantResolveMiddleware(new TestTenantProvider($tenants), $this->runtime($store), resolver: new HeaderTenantRequestResolver());
    }

    private function runtime(InMemoryTenantContextStore $store): TenantRuntimeExecutor
    {
        return new TenantRuntimeExecutor(new TenantContextFactory(), new TenantBootstrapPipeline(), $store);
    }
}
