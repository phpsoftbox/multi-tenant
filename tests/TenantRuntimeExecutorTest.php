<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests;

use PhpSoftBox\MultiTenant\Bootstrap\TenantBootstrapPipeline;
use PhpSoftBox\MultiTenant\Context\InMemoryTenantContextStore;
use PhpSoftBox\MultiTenant\Context\TenantContext;
use PhpSoftBox\MultiTenant\Context\TenantContextFactory;
use PhpSoftBox\MultiTenant\Context\TenantRuntimeScope;
use PhpSoftBox\MultiTenant\Contracts\TenantBootstrapperInterface;
use PhpSoftBox\MultiTenant\Contracts\TenantExtensionLoaderInterface;
use PhpSoftBox\MultiTenant\Tenant\Runtime\TenantRuntimeExecutor;
use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function in_array;

#[CoversClass(TenantRuntimeExecutor::class)]
final class TenantRuntimeExecutorTest extends TestCase
{
    #[Test]
    public function testRunBuildsContextBootstrapsAndRestoresStore(): void
    {
        $store = new InMemoryTenantContextStore();

        $previousTenant = new TenantDefinition('previous', 'Previous', null, 'tenant');

        $store->set(new TenantContext($previousTenant));

        $contextFactory = new TenantContextFactory([
            new class () implements TenantExtensionLoaderInterface {
                public function key(): string
                {
                    return 'marker';
                }

                public function supports(TenantRuntimeScope $scope): bool
                {
                    return true;
                }

                public function priority(): int
                {
                    return 10;
                }

                public function load(TenantDefinition $tenant, TenantContext $context): mixed
                {
                    return 'loaded:' . $tenant->id;
                }
            },
        ]);

        $log    = [];
        $append = static function (string $line) use (&$log): void {
            $log[] = $line;
        };

        $pipeline = new TenantBootstrapPipeline([
            new class ($append) implements TenantBootstrapperInterface {
                public function __construct(
                    private mixed $append,
                ) {
                }

                public function supports(TenantRuntimeScope $scope): bool
                {
                    return in_array($scope, [TenantRuntimeScope::Cli], true);
                }

                public function priority(): int
                {
                    return 100;
                }

                public function bootstrap(TenantContext $context): void
                {
                    ($this->append)('bootstrap:' . $context->tenant()->id);
                }

                public function teardown(TenantContext $context): void
                {
                    ($this->append)('teardown:' . $context->tenant()->id);
                }
            },
        ]);

        $executor = new TenantRuntimeExecutor($contextFactory, $pipeline, $store);

        $tenant = new TenantDefinition('tenant-1', 'Tenant 1', null, 'tenant');

        $result = $executor->run($tenant, static function (TenantContext $context): string {
            return (string) $context->get('marker');
        });

        $this->assertSame('loaded:tenant-1', $result);
        $this->assertSame(['bootstrap:tenant-1', 'teardown:tenant-1'], $log);
        $this->assertTrue($store->has());
        $this->assertSame('previous', $store->get()?->tenant()->id);
    }
}
