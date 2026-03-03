<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests;

use ArrayObject;
use PhpSoftBox\Config\Config;
use PhpSoftBox\MultiTenant\Contracts\TenantProvisionStepInterface;
use PhpSoftBox\MultiTenant\Provision\TenantProvisionContext;
use PhpSoftBox\MultiTenant\Provision\TenantProvisionPayload;
use PhpSoftBox\MultiTenant\Provision\TenantProvisionPipelineFactory;
use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;

use function array_key_exists;

#[CoversClass(TenantProvisionPipelineFactory::class)]
final class TenantProvisionPipelineFactoryTest extends TestCase
{
    #[Test]
    public function testStepPrioritiesCanBeOverriddenViaConfig(): void
    {
        $log = new ArrayObject();

        $stepA = new class ($log) implements TenantProvisionStepInterface {
            public function __construct(
                private ArrayObject
            $log,
            ) {
            }

            public function id(): string
            {
                return 'a';
            }

            public function priority(): int
            {
                return 300;
            }

            public function run(TenantProvisionContext $context): void
            {
                $this->log[] = 'a';
            }
        };

        $stepB = new class ($log) implements TenantProvisionStepInterface {
            public function __construct(
                private ArrayObject
            $log,
            ) {
            }

            public function id(): string
            {
                return 'b';
            }

            public function priority(): int
            {
                return 100;
            }

            public function run(TenantProvisionContext $context): void
            {
                $this->log[] = 'b';
            }
        };

        $config = new Config([[
            'tenancy' => [
                'provision' => [
                    'steps'           => [$stepA::class, $stepB::class],
                    'step_priorities' => [
                        $stepA::class => 10,
                        $stepB::class => 900,
                    ],
                ],
            ],
        ]]);

        $factory = new TenantProvisionPipelineFactory(
            config: $config,
            container: $this->container([
                $stepA::class => $stepA,
                $stepB::class => $stepB,
            ]),
        );

        $pipeline = $factory->create();
        $pipeline->run($this->context('tenant-1'));

        $this->assertSame(['b', 'a'], $log->getArrayCopy());
    }

    #[Test]
    public function testThrowsOnInvalidPriorityValue(): void
    {
        $step = new class () implements TenantProvisionStepInterface {
            public function id(): string
            {
                return 'a';
            }

            public function priority(): int
            {
                return 100;
            }

            public function run(TenantProvisionContext $context): void
            {
            }
        };

        $config = new Config([[
            'tenancy' => [
                'provision' => [
                    'steps'           => [$step::class],
                    'step_priorities' => [
                        $step::class => 'high',
                    ],
                ],
            ],
        ]]);

        $factory = new TenantProvisionPipelineFactory(
            config: $config,
            container: $this->container([$step::class => $step]),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Некорректное значение приоритета шага provisioning');

        $factory->create();
    }

    /**
     * @param array<string, object> $services
     */
    private function container(array $services): ContainerInterface
    {
        return new class ($services) implements ContainerInterface {
            /**
             * @param array<string, object> $services
             */
            public function __construct(
                private array
            $services,
            ) {
            }

            public function get(string $id): object
            {
                if (!$this->has($id)) {
                    throw new RuntimeException('Service not found: ' . $id);
                }

                return $this->services[$id];
            }

            public function has(string $id): bool
            {
                return array_key_exists($id, $this->services);
            }
        };
    }

    private function context(string $tenantId): TenantProvisionContext
    {
        $tenant   = new TenantDefinition($tenantId, 'Tenant', null, 'tenant');
        $template = new TenantDefinition('template-1', 'Template', null, 'tenant_template');
        $payload  = new TenantProvisionPayload($tenantId, 'template-1');

        return new TenantProvisionContext($tenant, $template, $payload);
    }
}
