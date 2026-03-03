<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests;

use PhpSoftBox\Cache\Cache;
use PhpSoftBox\Cache\Configurator\BuiltInDriverFactory;
use PhpSoftBox\Cache\Configurator\CacheConfig;
use PhpSoftBox\Cache\Configurator\CacheStoreFactory;
use PhpSoftBox\MultiTenant\Context\TenantContext;
use PhpSoftBox\MultiTenant\Switcher\CacheStoreNamespaceSwitcher;
use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CacheStoreNamespaceSwitcher::class)]
final class CacheStoreNamespaceSwitcherTest extends TestCase
{
    #[Test]
    public function testActivateIsolatesNamespaceAndDeactivateRestores(): void
    {
        $cache = new Cache(
            new CacheStoreFactory(
                stores: [
                    'default' => new CacheConfig(driver: 'array'),
                ],
                driverFactories: [new BuiltInDriverFactory()],
            ),
            'default',
        );

        $cache->set('foo', 'base');

        $context  = $this->context();
        $switcher = new CacheStoreNamespaceSwitcher($cache);

        $switcher->activate('tenant.alpha', $context);

        $this->assertNull($cache->get('foo'));

        $cache->set('foo', 'tenant');
        $this->assertSame('tenant', $cache->get('foo'));

        $switcher->deactivate($context);

        $this->assertSame('base', $cache->get('foo'));
    }

    private function context(): TenantContext
    {
        return new TenantContext(new TenantDefinition('tenant-alpha', 'Tenant Alpha', null, 'tenant'));
    }
}
