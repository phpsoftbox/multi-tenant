<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Orm;

use PhpSoftBox\Orm\Contracts\EntityAwareEntityManagerRegistryInterface as BaseEntityManagerRegistryInterface;
use PhpSoftBox\Orm\Contracts\EntityManagerInterface;
use PhpSoftBox\Orm\Contracts\EntityRuntimeRegistryInterface;
use PhpSoftBox\Orm\Contracts\UnitOfWorkInterface;

use function array_keys;
use function spl_object_id;
use function str_starts_with;
use function strpos;
use function substr;
use function trim;

final class TenantEntityManagerRegistry implements TenantEntityManagerRegistryInterface
{
    /**
     * @var array<string, EntityManagerInterface>
     */
    private array $entityManagers = [];

    public function __construct(
        private readonly BaseEntityManagerRegistryInterface $registry,
        private readonly string $tenantConnectionName = 'tenant',
    ) {
    }

    public function runtimeRegistry(): EntityRuntimeRegistryInterface
    {
        return $this->registry->runtimeRegistry();
    }

    public function default(bool $write = true): EntityManagerInterface
    {
        $mode     = $write ? 'write' : 'read';
        $cacheKey = $mode . ':__default__';

        if (isset($this->entityManagers[$cacheKey])) {
            return $this->entityManagers[$cacheKey];
        }

        $entityManager                   = $this->registry->default($write);
        $this->entityManagers[$cacheKey] = $entityManager;

        return $entityManager;
    }

    public function forConnection(string $connectionName, bool $write = true): EntityManagerInterface
    {
        $connectionName = trim($connectionName);
        if ($connectionName === '') {
            return $this->default($write);
        }

        $mode     = $write ? 'write' : 'read';
        $cacheKey = $mode . ':' . $connectionName;

        if (isset($this->entityManagers[$cacheKey])) {
            return $this->entityManagers[$cacheKey];
        }

        $entityManager                   = $this->registry->forConnection($connectionName, $write);
        $this->entityManagers[$cacheKey] = $entityManager;

        return $entityManager;
    }

    public function tenant(bool $write = true): EntityManagerInterface
    {
        return $this->forConnection($this->tenantConnectionName, $write);
    }

    public function connectionNameForEntity(string $entityClass): ?string
    {
        return $this->registry->connectionNameForEntity($entityClass);
    }

    public function forEntity(string $entityClass, bool $write = true): EntityManagerInterface
    {
        $connectionName = $this->connectionNameForEntity($entityClass);

        return $connectionName === null
            ? $this->default($write)
            : $this->forConnection($connectionName, $write);
    }

    public function resetTenant(): void
    {
        $this->reset($this->tenantConnectionName);
    }

    public function reset(?string $connectionName = null): void
    {
        $normalizedConnection = trim((string) $connectionName);
        if ($normalizedConnection === '') {
            $this->clearAndForget(array_keys($this->entityManagers));

            return;
        }

        $cacheKeys = [];
        foreach (array_keys($this->entityManagers) as $cacheKey) {
            $separatorPosition = strpos($cacheKey, ':');
            if ($separatorPosition === false) {
                continue;
            }

            $cachedConnection = substr($cacheKey, $separatorPosition + 1);
            if (
                $cachedConnection === $normalizedConnection
                || str_starts_with($cachedConnection, $normalizedConnection . '.')
            ) {
                $cacheKeys[] = $cacheKey;
            }
        }

        $this->clearAndForget($cacheKeys);
    }

    /**
     * @param list<string> $cacheKeys
     */
    private function clearAndForget(array $cacheKeys): void
    {
        /** @var array<int, UnitOfWorkInterface> $unitsOfWork */
        $unitsOfWork = [];

        foreach ($cacheKeys as $cacheKey) {
            $entityManager = $this->entityManagers[$cacheKey] ?? null;
            if ($entityManager === null) {
                continue;
            }

            $unitOfWork                              = $entityManager->unitOfWork();
            $unitsOfWork[spl_object_id($unitOfWork)] = $unitOfWork;
        }

        foreach ($unitsOfWork as $unitOfWork) {
            $unitOfWork->clear();
        }

        foreach ($cacheKeys as $cacheKey) {
            unset($this->entityManagers[$cacheKey]);
        }
    }
}
