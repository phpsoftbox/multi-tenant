<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Provision;

use PhpSoftBox\Config\Config;
use PhpSoftBox\MultiTenant\Contracts\TenantProvisionStepInterface;
use PhpSoftBox\MultiTenant\Provision\Step\CliCommandListProvisionStep;
use PhpSoftBox\MultiTenant\Provision\Step\DatabaseCloneProvisionStep;
use PhpSoftBox\MultiTenant\Provision\Step\OverriddenPriorityProvisionStep;
use Psr\Container\ContainerInterface;
use RuntimeException;

use function array_key_exists;
use function filter_var;
use function is_array;
use function is_int;
use function is_string;
use function trim;

use const FILTER_VALIDATE_INT;

final readonly class TenantProvisionPipelineFactory
{
    public function __construct(
        private Config $config,
        private ContainerInterface $container,
    ) {
    }

    public function create(): TenantProvisionPipeline
    {
        $stepIds = $this->config->get(
            'tenancy.provision.steps',
            [
                DatabaseCloneProvisionStep::class,
                CliCommandListProvisionStep::class,
            ],
        );

        if (!is_array($stepIds)) {
            throw new RuntimeException('tenancy.provision.steps должен быть массивом class-string сервисов.');
        }

        $priorityOverrides = $this->config->get('tenancy.provision.step_priorities', []);
        if (!is_array($priorityOverrides)) {
            throw new RuntimeException('tenancy.provision.step_priorities должен быть массивом key=>int.');
        }

        $steps = [];
        foreach ($stepIds as $serviceId) {
            if (!is_string($serviceId) || trim($serviceId) === '') {
                continue;
            }

            $step = $this->container->get(trim($serviceId));
            if (!$step instanceof TenantProvisionStepInterface) {
                throw new RuntimeException(
                    'Provision step должен реализовывать TenantProvisionStepInterface: ' . trim($serviceId),
                );
            }

            $priority = $this->resolvePriorityOverride(
                $priorityOverrides,
                trim($serviceId),
                $step::class,
            );

            if ($priority !== null) {
                $step = new OverriddenPriorityProvisionStep($step, $priority);
            }

            $steps[] = $step;
        }

        return new TenantProvisionPipeline($steps);
    }

    /**
     * @param array<mixed> $overrides
     */
    private function resolvePriorityOverride(
        array $overrides,
        string $serviceId,
        string $stepClass,
    ): ?int {
        $raw = null;

        if (array_key_exists($serviceId, $overrides)) {
            $raw = $overrides[$serviceId];
        } elseif (array_key_exists($stepClass, $overrides)) {
            $raw = $overrides[$stepClass];
        }

        if ($raw === null) {
            return null;
        }

        if (is_int($raw)) {
            return $raw;
        }

        if (is_string($raw) && trim($raw) !== '') {
            $value = filter_var(trim($raw), FILTER_VALIDATE_INT);
            if (is_int($value)) {
                return $value;
            }
        }

        throw new RuntimeException(
            'Некорректное значение приоритета шага provisioning для "' . $serviceId . '". Ожидается int.',
        );
    }
}
