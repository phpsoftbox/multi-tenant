<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Orm;

use PhpSoftBox\Orm\Contracts\EntityManagerInterface;
use PhpSoftBox\Orm\Contracts\EntityManagerRegistryInterface as BaseEntityManagerRegistryInterface;

use function array_keys;
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

    public function reset(?string $connectionName = null): void
    {
        $normalizedConnection = trim((string) $connectionName);
        if ($normalizedConnection === '') {
            $this->entityManagers = [];

            return;
        }

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
                unset($this->entityManagers[$cacheKey]);
            }
        }
    }
}
