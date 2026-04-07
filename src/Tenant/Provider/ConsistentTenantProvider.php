<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tenant\Provider;

use PhpSoftBox\MultiTenant\Contracts\ReloadableTenantProviderInterface;
use PhpSoftBox\MultiTenant\Contracts\TenantProviderInterface;
use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;
use PhpSoftBox\MultiTenant\Tenant\TenantDefinitionNormalizer;
use RuntimeException;

use function array_diff;
use function array_keys;
use function implode;

final class ConsistentTenantProvider implements TenantProviderInterface, ReloadableTenantProviderInterface
{
    private bool $checked = false;

    public function __construct(
        private readonly TenantProviderInterface $primary,
        private readonly TenantProviderInterface $secondary,
        private readonly bool $enforceParity = true,
        private readonly ?TenantDefinitionNormalizer $normalizer = null,
    ) {
    }

    public function all(bool $onlyEnabled = true): array
    {
        $this->ensureParity();

        return $this->primary->all($onlyEnabled);
    }

    public function findById(string $id): ?TenantDefinition
    {
        $this->ensureParity();

        return $this->primary->findById($id);
    }

    public function findByHost(string $host): ?TenantDefinition
    {
        $this->ensureParity();

        return $this->primary->findByHost($host);
    }

    public function reload(): void
    {
        $this->checked = false;

        if ($this->primary instanceof ReloadableTenantProviderInterface) {
            $this->primary->reload();
        }

        if ($this->secondary instanceof ReloadableTenantProviderInterface) {
            $this->secondary->reload();
        }
    }

    private function ensureParity(): void
    {
        if ($this->checked || !$this->enforceParity) {
            return;
        }

        $normalizer = $this->normalizer ?? new TenantDefinitionNormalizer();
        $primary    = $normalizer->normalize($this->primary->all(false));
        $secondary  = $normalizer->normalize($this->secondary->all(false));

        if ($primary !== $secondary) {
            $primaryIds   = array_keys($primary);
            $secondaryIds = array_keys($secondary);

            $onlyInPrimary   = array_diff($primaryIds, $secondaryIds);
            $onlyInSecondary = array_diff($secondaryIds, $primaryIds);

            $details = [];
            if ($onlyInPrimary !== []) {
                $details[] = 'только в primary: ' . implode(', ', $onlyInPrimary);
            }

            if ($onlyInSecondary !== []) {
                $details[] = 'только в secondary: ' . implode(', ', $onlyInSecondary);
            }

            throw new RuntimeException(
                'Конфигурации tenant в primary/secondary provider не совпадают.'
                . ($details === [] ? '' : ' ' . implode('; ', $details)),
            );
        }

        $this->checked = true;
    }
}
