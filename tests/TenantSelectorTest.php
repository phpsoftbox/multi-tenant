<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests;

use PhpSoftBox\MultiTenant\Contracts\TenantProviderInterface;
use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;
use PhpSoftBox\MultiTenant\Tenant\TenantSelector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function array_filter;
use function array_values;

#[CoversClass(TenantSelector::class)]
#[CoversMethod(TenantSelector::class, 'select')]
final class TenantSelectorTest extends TestCase
{
    /**
     * Проверяет режим all: возвращаются только enabled tenant.
     */
    #[Test]
    public function testSelectAllReturnsOnlyEnabledByDefault(): void
    {
        $selector = new TenantSelector($this->provider([
            new TenantDefinition('t1', 'Tenant 1', null, 'conn_t1', enabled: true),
            new TenantDefinition('t2', 'Tenant 2', null, 'conn_t2', enabled: false),
        ]));

        $selected = $selector->select('all');

        $this->assertCount(1, $selected);
        $this->assertSame('t1', $selected[0]->id);
    }

    /**
     * Проверяет выбор списка tenant и удаление дубликатов.
     */
    #[Test]
    public function testSelectListDeduplicatesTenants(): void
    {
        $selector = new TenantSelector($this->provider([
            new TenantDefinition('t1', 'Tenant 1', null, 'conn_t1', enabled: true),
            new TenantDefinition('t2', 'Tenant 2', null, 'conn_t2', enabled: false),
        ]));

        $selected = $selector->select(' t1, t1 , t2 ', false);

        $this->assertCount(2, $selected);
        $this->assertSame('t1', $selected[0]->id);
        $this->assertSame('t2', $selected[1]->id);
    }

    /**
     * Проверяет ошибку для неизвестного tenant.
     */
    #[Test]
    public function testSelectThrowsForUnknownTenant(): void
    {
        $selector = new TenantSelector($this->provider([
            new TenantDefinition('t1', 'Tenant 1', null, 'conn_t1', enabled: true),
        ]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tenant не найден: unknown');

        $selector->select('unknown');
    }

    /**
     * Проверяет ошибку для disabled tenant в режиме onlyEnabled.
     */
    #[Test]
    public function testSelectThrowsForDisabledTenantWhenOnlyEnabled(): void
    {
        $selector = new TenantSelector($this->provider([
            new TenantDefinition('t2', 'Tenant 2', null, 'conn_t2', enabled: false),
        ]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tenant отключен: t2');

        $selector->select('t2', true);
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
                private array $tenants,
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
}
