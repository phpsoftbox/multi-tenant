<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tenant\Provider;

use PhpSoftBox\Database\Connection\ConnectionManagerInterface;
use PhpSoftBox\MultiTenant\Contracts\Entity\DomainEntityInterface;
use PhpSoftBox\MultiTenant\Contracts\Entity\TenantEntityInterface;
use PhpSoftBox\MultiTenant\Contracts\ReloadableTenantProviderInterface;
use PhpSoftBox\MultiTenant\Contracts\TenantProviderInterface;
use PhpSoftBox\MultiTenant\Entity\Tenant\Domain;
use PhpSoftBox\MultiTenant\Entity\Tenant\Tenant;
use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;
use PhpSoftBox\Orm\ConnectionEntityManagerFactory;
use PhpSoftBox\Orm\Contracts\ConnectionEntityManagerFactoryInterface;
use PhpSoftBox\Orm\Contracts\EntityManagerInterface;
use PhpSoftBox\Orm\Contracts\EntityRepositoryInterface;
use RuntimeException;

use function array_filter;
use function array_key_exists;
use function array_values;
use function in_array;
use function is_array;
use function is_string;
use function is_subclass_of;
use function strtolower;
use function trim;

final class DatabaseTenantProvider implements TenantProviderInterface, ReloadableTenantProviderInterface
{
    /** @var list<TenantDefinition>|null */
    private ?array $tenants = null;

    private ?EntityManagerInterface $entityManager = null;

    /**
     * @param class-string<TenantEntityInterface> $tenantEntityClass
     * @param class-string<DomainEntityInterface> $domainEntityClass
     */
    public function __construct(
        private readonly ConnectionManagerInterface $connections,
        private readonly string $connectionName = 'default',
        private readonly string $defaultDatabaseConnection = 'tenant',
        private readonly string $tenantEntityClass = Tenant::class,
        private readonly string $domainEntityClass = Domain::class,
        private readonly ?ConnectionEntityManagerFactoryInterface $entityManagerFactory = null,
    ) {
    }

    public function all(bool $onlyEnabled = true): array
    {
        $tenants = $this->load();

        if (!$onlyEnabled) {
            return $tenants;
        }

        return array_values(array_filter(
            $tenants,
            static fn (TenantDefinition $tenant): bool => $tenant->enabled,
        ));
    }

    public function findById(string $id): ?TenantDefinition
    {
        $id = trim($id);
        if ($id === '') {
            return null;
        }

        foreach ($this->load() as $tenant) {
            if ($tenant->id === $id) {
                return $tenant;
            }
        }

        return null;
    }

    public function findByHost(string $host): ?TenantDefinition
    {
        $host = strtolower(trim($host));
        if ($host === '') {
            return null;
        }

        foreach ($this->load() as $tenant) {
            foreach ($tenant->domains as $domain) {
                if (strtolower($domain) === $host) {
                    return $tenant;
                }
            }

            if (is_string($tenant->host) && strtolower($tenant->host) === $host) {
                return $tenant;
            }
        }

        return null;
    }

    public function reload(): void
    {
        $this->tenants = null;
    }

    /**
     * @return list<TenantDefinition>
     */
    private function load(): array
    {
        if ($this->tenants !== null) {
            return $this->tenants;
        }

        $this->assertEntityClass($this->tenantEntityClass, TenantEntityInterface::class);
        $this->assertEntityClass($this->domainEntityClass, DomainEntityInterface::class);

        $tenantRepository = $this->entityManager()->repository($this->tenantEntityClass);
        if (!$tenantRepository instanceof EntityRepositoryInterface) {
            throw new RuntimeException('Репозиторий tenant entity должен реализовывать EntityRepositoryInterface.');
        }

        $domainRepository = $this->entityManager()->repository($this->domainEntityClass);
        if (!$domainRepository instanceof EntityRepositoryInterface) {
            throw new RuntimeException('Репозиторий domain entity должен реализовывать EntityRepositoryInterface.');
        }

        $tenantEntities = $tenantRepository->all()->all();
        $domainEntities = $domainRepository->all()->all();

        $domainsByTenant = [];
        foreach ($domainEntities as $domainEntity) {
            if (!$domainEntity instanceof DomainEntityInterface) {
                continue;
            }

            $tenantId = $domainEntity->tenantId();
            if ($tenantId <= 0) {
                continue;
            }

            $domainsByTenant[$tenantId][] = $domainEntity;
        }

        $result = [];
        foreach ($tenantEntities as $tenantEntity) {
            if (!$tenantEntity instanceof TenantEntityInterface) {
                continue;
            }

            $tenantId = $tenantEntity->tenantId();
            if ($tenantId <= 0) {
                continue;
            }

            $data          = is_array($tenantEntity->data()) ? $tenantEntity->data() : [];
            $domainRecords = $domainsByTenant[$tenantId] ?? [];
            $domains       = [];
            $primaryDomain = null;

            foreach ($domainRecords as $domainRecord) {
                if (!$domainRecord->enabled()) {
                    continue;
                }

                $domain = trim($domainRecord->domain());
                if ($domain === '') {
                    continue;
                }

                if (!in_array($domain, $domains, true)) {
                    $domains[] = $domain;
                }

                if ($domainRecord->isPrimary() && $primaryDomain === null) {
                    $primaryDomain = $domain;
                }
            }

            if ($primaryDomain === null && $domains !== []) {
                $primaryDomain = $domains[0];
            }

            if ($primaryDomain === null) {
                $candidate = $this->dataString($data, 'primary_domain');
                if ($candidate !== null && $candidate !== '') {
                    $primaryDomain = $candidate;
                    if (!in_array($candidate, $domains, true)) {
                        $domains[] = $candidate;
                    }
                }
            }

            $pushrAppId = $this->dataString($data, 'pushr', 'app_id')
                ?? $this->dataString($data, 'pushr_app_id');
            $pushrSecret = $this->dataString($data, 'pushr', 'secret')
                ?? $this->dataString($data, 'pushr_secret');

            $name               = trim($tenantEntity->tenantName());
            $databaseConnection = trim($tenantEntity->databaseConnection());

            $result[] = new TenantDefinition(
                id: (string) $tenantId,
                name: $name !== '' ? $name : (string) $tenantId,
                host: $primaryDomain,
                databaseConnection: $databaseConnection !== '' ? $databaseConnection : $this->defaultDatabaseConnection,
                pushrAppId: $pushrAppId,
                pushrSecret: $pushrSecret,
                enabled: $tenantEntity->enabled(),
                databaseName: $tenantEntity->databaseName(),
                primaryDomain: $primaryDomain,
                domains: $domains,
                data: $data,
            );
        }

        $this->tenants = $result;

        return $this->tenants;
    }

    private function entityManager(): EntityManagerInterface
    {
        if ($this->entityManager !== null) {
            return $this->entityManager;
        }

        $factory             = $this->entityManagerFactory ?? new ConnectionEntityManagerFactory($this->connections);
        $this->entityManager = $factory->create(connectionName: $this->connectionName, write: false);

        return $this->entityManager;
    }

    private function assertEntityClass(string $class, string $expectedInterface): void
    {
        if (!is_subclass_of($class, $expectedInterface)) {
            throw new RuntimeException('Entity class должен реализовывать ' . $expectedInterface . ': ' . $class);
        }
    }

    private function dataString(?array $data, string ...$path): ?string
    {
        $cursor = $this->dataValue($data, ...$path);
        if (!is_string($cursor)) {
            return null;
        }

        $cursor = trim($cursor);

        return $cursor !== '' ? $cursor : null;
    }

    private function dataValue(?array $data, string ...$path): mixed
    {
        if (!is_array($data) || $path === []) {
            return null;
        }

        $cursor = $data;
        foreach ($path as $key) {
            if (!is_array($cursor) || !array_key_exists($key, $cursor)) {
                return null;
            }

            $cursor = $cursor[$key];
        }

        return $cursor;
    }

}
