<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests;

use PhpSoftBox\Auth\Guard\GuardInterface;
use PhpSoftBox\Auth\Manager\AuthManager;
use PhpSoftBox\Http\Message\ServerRequest;
use PhpSoftBox\MultiTenant\Context\InMemoryTenantContextStore;
use PhpSoftBox\MultiTenant\Context\TenantContext;
use PhpSoftBox\MultiTenant\Context\TenantContextResolver;
use PhpSoftBox\MultiTenant\Orm\ChangeLog\TenantUserEntityChangeContextResolver;
use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

#[CoversClass(TenantUserEntityChangeContextResolver::class)]
final class TenantUserEntityChangeContextResolverTest extends TestCase
{
    #[Test]
    public function resolveUsesRequestAttributeAndTenantMetadata(): void
    {
        $request = new ServerRequest('post', 'https://tenant.example.test/users')
            ->withAttribute('user_id', '42');

        $resolver = new TenantUserEntityChangeContextResolver(
            tenantResolver: $this->tenantResolver('tenant-7'),
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
    public function resolveUsesConfiguredAuthGuardsAsFallback(): void
    {
        $request = new ServerRequest('GET', 'https://tenant.example.test/tasks');
        $auth    = new AuthManager([
            'web'    => new ArrayUserGuard(null),
            'tenant' => new ArrayUserGuard(['id' => 'user-external-id']),
        ]);

        $resolver = new TenantUserEntityChangeContextResolver(
            tenantResolver: $this->tenantResolver(null),
            request: $request,
            auth: $auth,
        );

        $context = $resolver->resolve();

        self::assertSame('user-external-id', $context->initiatorId);
        self::assertSame('user', $context->initiatorType);
        self::assertArrayNotHasKey('tenant_id', $context->metadata);
    }

    #[Test]
    public function resolveReturnsSystemContextWithoutRequestUser(): void
    {
        $resolver = new TenantUserEntityChangeContextResolver(
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

final readonly class ArrayUserGuard implements GuardInterface
{
    /**
     * @param array<string, mixed>|null $user
     */
    public function __construct(
        private ?array $user,
    ) {
    }

    public function user(ServerRequestInterface $request): mixed
    {
        return $this->user;
    }
}
