<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests;

use PhpSoftBox\MultiTenant\Context\InMemoryTenantContextStore;
use PhpSoftBox\MultiTenant\Context\TenantContext;
use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(InMemoryTenantContextStore::class)]
#[CoversMethod(InMemoryTenantContextStore::class, 'set')]
#[CoversMethod(InMemoryTenantContextStore::class, 'get')]
#[CoversMethod(InMemoryTenantContextStore::class, 'has')]
#[CoversMethod(InMemoryTenantContextStore::class, 'clear')]
final class InMemoryTenantContextStoreTest extends TestCase
{
    #[Test]
    public function testStoreSetGetAndClearContext(): void
    {
        $store = new InMemoryTenantContextStore();

        $this->assertFalse($store->has());
        $this->assertNull($store->get());

        $context = new TenantContext(new TenantDefinition('tenant-1', 'Tenant 1', null, 'tenant_1'));

        $store->set($context);

        $this->assertTrue($store->has());
        $this->assertSame($context, $store->get());

        $store->clear();

        $this->assertFalse($store->has());
        $this->assertNull($store->get());
    }
}
