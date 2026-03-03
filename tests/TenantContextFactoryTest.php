<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests;

use PhpSoftBox\MultiTenant\Context\TenantContext;
use PhpSoftBox\MultiTenant\Context\TenantContextFactory;
use PhpSoftBox\MultiTenant\Context\TenantRuntimeScope;
use PhpSoftBox\MultiTenant\Contracts\TenantExtensionLoaderInterface;
use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function in_array;

#[CoversClass(TenantContextFactory::class)]
#[CoversMethod(TenantContextFactory::class, 'create')]
final class TenantContextFactoryTest extends TestCase
{
    #[Test]
    public function testCreateBuildsExtensionsByPriorityAndScope(): void
    {
        $log = [];

        $factory = new TenantContextFactory([
            $this->loader(
                key: 'derived',
                priority: 10,
                supports: [TenantRuntimeScope::Cli],
                load: static function (TenantContext $context, TenantDefinition $tenant) use (&$log): array {
                    $log[] = 'derived';

                    return ['base' => $context->get('base'), 'tenant_id' => $tenant->id];
                },
            ),
            $this->loader(
                key: 'base',
                priority: 100,
                supports: [TenantRuntimeScope::Cli],
                load: static function (TenantContext $context, TenantDefinition $tenant) use (&$log): array {
                    $log[] = 'base';

                    return ['bot' => 'account', 'tenant' => $tenant->id, 'has_derived' => $context->has('derived')];
                },
            ),
            $this->loader(
                key: 'http_only',
                priority: 1000,
                supports: [TenantRuntimeScope::Http],
                load: static fn (TenantContext $context, TenantDefinition $tenant): string => 'skip',
            ),
        ]);

        $context = $factory->create($this->tenant(), TenantRuntimeScope::Cli);

        $this->assertSame(['base', 'derived'], $log);
        $this->assertSame(['bot' => 'account', 'tenant' => 'tenant-1', 'has_derived' => false], $context->get('base'));
        $this->assertSame(
            ['base' => ['bot' => 'account', 'tenant' => 'tenant-1', 'has_derived' => false], 'tenant_id' => 'tenant-1'],
            $context->get('derived'),
        );
        $this->assertFalse($context->has('http_only'));
    }

    #[Test]
    public function testCreateThrowsOnDuplicateExtensionKey(): void
    {
        $factory = new TenantContextFactory([
            $this->loader(
                'telegram',
                100,
                [TenantRuntimeScope::Cli],
                static fn (TenantContext $context, TenantDefinition $tenant): array => ['a' => 1],
            ),
            $this->loader(
                'telegram',
                10,
                [TenantRuntimeScope::Cli],
                static fn (TenantContext $context, TenantDefinition $tenant): array => ['a' => 2],
            ),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Дублирующийся extension key: telegram');

        $factory->create($this->tenant(), TenantRuntimeScope::Cli);
    }

    #[Test]
    public function testTenantContextGetTypedReturnsObjectExtension(): void
    {
        $factory = new TenantContextFactory([
            $this->loader(
                key: 'telegram',
                priority: 100,
                supports: [TenantRuntimeScope::Cli],
                load: static fn (
                    TenantContext $context,
                    TenantDefinition $tenant,
                ): TenantContextFactoryTestTelegramConfig => new TenantContextFactoryTestTelegramConfig('account'),
            ),
        ]);

        $context = $factory->create($this->tenant());

        $config = $context->getTyped(TenantContextFactoryTestTelegramConfig::class);
        $this->assertInstanceOf(TenantContextFactoryTestTelegramConfig::class, $config);
        $this->assertSame('account', $config->defaultBot);
    }

    #[Test]
    public function testCreateStoresExtensionByLoaderClassKey(): void
    {
        $loader = new TenantContextFactoryTestClassKeyLoader();

        $factory = new TenantContextFactory([$loader]);

        $context = $factory->create($this->tenant(), TenantRuntimeScope::Cli);

        $this->assertSame(['bot' => 'account'], $context->get('telegram'));
        $this->assertSame(['bot' => 'account'], $context->get($loader::class));
    }

    private function tenant(): TenantDefinition
    {
        return new TenantDefinition(
            id: 'tenant-1',
            name: 'Tenant 1',
            host: 'tenant1.chegdesklad.local',
            databaseConnection: 'tenant_1',
        );
    }

    /**
     * @param list<TenantRuntimeScope> $supports
     */
    private function loader(
        string $key,
        int $priority,
        array $supports,
        callable $load,
    ): TenantExtensionLoaderInterface {
        return new class ($key, $priority, $supports, $load) implements TenantExtensionLoaderInterface {
            /**
             * @param list<TenantRuntimeScope> $supports
             */
            public function __construct(
                private readonly string $key,
                private readonly int $priority,
                private readonly array $supports,
                private readonly mixed $load,
            ) {
            }

            public function key(): string
            {
                return $this->key;
            }

            public function supports(TenantRuntimeScope $scope): bool
            {
                return in_array($scope, $this->supports, true);
            }

            public function priority(): int
            {
                return $this->priority;
            }

            public function load(TenantDefinition $tenant, TenantContext $context): mixed
            {
                return ($this->load)($context, $tenant);
            }
        };
    }
}

final readonly class TenantContextFactoryTestTelegramConfig
{
    public function __construct(
        public string $defaultBot,
    ) {
    }
}

final class TenantContextFactoryTestClassKeyLoader implements TenantExtensionLoaderInterface
{
    public function key(): string
    {
        return 'telegram';
    }

    public function supports(TenantRuntimeScope $scope): bool
    {
        return $scope === TenantRuntimeScope::Cli;
    }

    public function priority(): int
    {
        return 100;
    }

    public function load(TenantDefinition $tenant, TenantContext $context): mixed
    {
        return ['bot' => 'account'];
    }
}
