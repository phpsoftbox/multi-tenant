<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests;

use PhpSoftBox\MultiTenant\Context\InMemoryTenantContextStore;
use PhpSoftBox\MultiTenant\Context\TenantContext;
use PhpSoftBox\MultiTenant\Context\TenantContextResolver;
use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(TenantContextResolver::class)]
final class TenantContextResolverTest extends TestCase
{
    #[Test]
    public function getReturnsNullWhenNoContext(): void
    {
        $resolver = new TenantContextResolver(new InMemoryTenantContextStore());

        self::assertFalse($resolver->has());
        self::assertNull($resolver->context());
        self::assertNull($resolver->get());
    }

    #[Test]
    public function getReturnsTenantWhenContextExists(): void
    {
        $store  = new InMemoryTenantContextStore();
        $tenant = new TenantDefinition(
            id: 'tenant-1',
            name: 'Tenant 1',
            host: 'tenant1.example.test',
            databaseConnection: 'tenant',
        );

        $store->set(new TenantContext($tenant));

        $resolver = new TenantContextResolver($store);

        self::assertTrue($resolver->has());
        self::assertSame($tenant, $resolver->get());
        self::assertSame($tenant, $resolver->getOrFail());
    }

    #[Test]
    public function getOrFailThrowsWhenNoContext(): void
    {
        $resolver = new TenantContextResolver(new InMemoryTenantContextStore());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tenant context is missing.');

        $resolver->getOrFail();
    }
}
