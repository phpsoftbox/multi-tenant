<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests;

use PhpSoftBox\Auth\Manager\AuthManager;
use PhpSoftBox\Http\Message\ServerRequest;
use PhpSoftBox\MultiTenant\Context\InMemoryTenantContextStore;
use PhpSoftBox\MultiTenant\Context\TenantContext;
use PhpSoftBox\MultiTenant\Context\TenantContextResolver;
use PhpSoftBox\MultiTenant\Orm\ChangeLog\Initiator\AuthManagerEntityChangeInitiatorResolver;
use PhpSoftBox\MultiTenant\Orm\ChangeLog\Initiator\ChainEntityChangeInitiatorResolver;
use PhpSoftBox\MultiTenant\Orm\ChangeLog\Initiator\RequestAttributeEntityChangeInitiatorResolver;
use PhpSoftBox\MultiTenant\Orm\ChangeLog\Initiator\StaticEntityChangeInitiatorResolver;
use PhpSoftBox\MultiTenant\Orm\ChangeLog\TenantEntityChangeContextResolver;
use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;
use PhpSoftBox\MultiTenant\Tests\Support\TestAuthUser;
use PhpSoftBox\MultiTenant\Tests\Support\TestUserGuard;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TenantEntityChangeContextResolver::class)]
#[CoversClass(AuthManagerEntityChangeInitiatorResolver::class)]
#[CoversClass(ChainEntityChangeInitiatorResolver::class)]
#[CoversClass(RequestAttributeEntityChangeInitiatorResolver::class)]
#[CoversClass(StaticEntityChangeInitiatorResolver::class)]
final class TenantEntityChangeContextResolverTest extends TestCase
{
    #[Test]
    public function resolveUsesRequestAttributeAndTenantMetadata(): void
    {
        $request = new ServerRequest('post', 'https://tenant.example.test/users')
            ->withAttribute('user_id', '42');

        $resolver = new TenantEntityChangeContextResolver(
            tenantResolver: $this->tenantResolver('tenant-7'),
            initiatorResolver: new RequestAttributeEntityChangeInitiatorResolver($request),
            request: $request,
        );

        $context = $resolver->resolve();

        self::assertSame(42, $context->initiatorId);
        self::assertSame('user', $context->initiatorType);
        self::assertSame('tenant-7', $context->metadata['tenant_id'] ?? null);
        self::assertSame('POST', $context->metadata['request_method'] ?? null);
        self::assertSame('/users', $context->metadata['request_path'] ?? null);
    }

    #[Test]
    public function resolveUsesConfiguredRequestAttributes(): void
    {
        $request = new ServerRequest('post', 'https://tenant.example.test/users')
            ->withAttribute('auth_user_id', '77')
            ->withAttribute('_authUser', new TestAuthUser('88'));

        $resolver = new TenantEntityChangeContextResolver(
            tenantResolver: $this->tenantResolver(null),
            initiatorResolver: new RequestAttributeEntityChangeInitiatorResolver(
                request: $request,
                userIdAttribute: null,
                userAttribute: '_authUser',
            ),
            request: $request,
        );

        $context = $resolver->resolve();

        self::assertSame('88', $context->initiatorId);
        self::assertSame('user', $context->initiatorType);
    }

    #[Test]
    public function resolveUsesConfiguredAuthGuardAsFallback(): void
    {
        $request = new ServerRequest('GET', 'https://tenant.example.test/tasks');
        $auth    = new AuthManager([
            'web'    => new TestUserGuard(null),
            'tenant' => new TestUserGuard(new TestAuthUser('user-external-id')),
        ]);

        $resolver = new TenantEntityChangeContextResolver(
            tenantResolver: $this->tenantResolver(null),
            initiatorResolver: new ChainEntityChangeInitiatorResolver([
                new RequestAttributeEntityChangeInitiatorResolver($request),
                new AuthManagerEntityChangeInitiatorResolver(
                    request: $request,
                    auth: $auth,
                    guard: 'tenant',
                ),
            ]),
            request: $request,
        );

        $context = $resolver->resolve();

        self::assertSame('user-external-id', $context->initiatorId);
        self::assertSame('user', $context->initiatorType);
        self::assertArrayNotHasKey('tenant_id', $context->metadata);
    }

    #[Test]
    public function resolveUsesStaticInitiatorWithoutHttpRequest(): void
    {
        $resolver = new TenantEntityChangeContextResolver(
            tenantResolver: $this->tenantResolver('tenant-7'),
            initiatorResolver: new StaticEntityChangeInitiatorResolver(42),
        );

        $context = $resolver->resolve();

        self::assertSame(42, $context->initiatorId);
        self::assertSame('user', $context->initiatorType);
        self::assertSame('tenant-7', $context->metadata['tenant_id'] ?? null);
        self::assertArrayNotHasKey('request_method', $context->metadata);
        self::assertArrayNotHasKey('request_path', $context->metadata);
    }

    #[Test]
    public function resolveReturnsSystemContextWithoutInitiator(): void
    {
        $resolver = new TenantEntityChangeContextResolver(
            tenantResolver: $this->tenantResolver(null),
            request: new ServerRequest('GET', '/'),
        );

        $context = $resolver->resolve();

        self::assertNull($context->initiatorId);
        self::assertSame('system', $context->initiatorType);
    }

    private function tenantResolver(?string $tenantId): TenantContextResolver
    {
        $store = new InMemoryTenantContextStore();

        if ($tenantId !== null) {
            $store->set(new TenantContext(new TenantDefinition(
                id: $tenantId,
                name: 'Tenant ' . $tenantId,
                host: 'tenant.example.test',
                databaseConnection: 'tenant',
            )));
        }

        return new TenantContextResolver($store);
    }
}
